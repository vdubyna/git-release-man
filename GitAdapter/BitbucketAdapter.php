<?php

namespace Mirocode\GitReleaseMan\GitAdapter;

use Bitbucket\API\Http\Listener\BasicAuthListener;
use Composer\Semver\Semver;
use InvalidArgumentException;
use Bitbucket\API\Api as Client;
use Mirocode\GitReleaseMan\Entity\Feature;
use Mirocode\GitReleaseMan\Entity\MergeRequest;
use Mirocode\GitReleaseMan\Entity\Release;
use Mirocode\GitReleaseMan\GitAdapter\GitAdapterAbstract;
use Mirocode\GitReleaseMan\GitAdapter\GitAdapterInterface;
use Mirocode\GitReleaseMan\Configuration;
use Mirocode\GitReleaseMan\Version;
use GuzzleHttp;

class BitbucketAdapter extends GitAdapterAbstract implements GitAdapterInterface, GitServiceInterface
{
    protected $apiClient;

    /**
     * @return Feature[]
     */
    public function getFeaturesList()
    {
        $username   = $this->getConfiguration()->getUsername();
        $repository = $this->getConfiguration()->getRepository();

        /** @var \Bitbucket\API\Repositories\Refs\Branches $branchesApi */
        $branchesApi = $this->getApiClient()->api('Repositories\Refs\Branches');

        $branches = GuzzleHttp\json_decode($branchesApi->all($username, $repository)->getContent(), true);
        $branchesInfo = $branches['values'];

        if (empty($branchesInfo)) {
            return array();
        }

        $branchesNames = array_map(function ($branch) {
            return $branch['name'];
        }, $branchesInfo);

        $features = array_filter($branchesNames, function ($branch) {
            return (strpos($branch, $this->getConfiguration()->getFeaturePrefix()) === 0);
        });

        $features = array_map(function ($branch) {
            return $this->buildFeature($branch);
        }, $features);

        return $features;
    }

    /**
     * Force Adapters to load feature info from repository
     *
     * @param $featureName
     *
     * @return Feature
     */
    public function buildFeature($featureName)
    {
        $username    = $this->getConfiguration()->getUsername();
        $repository  = $this->getConfiguration()->getRepository();

        try {
            /** @var \Bitbucket\API\Repositories\Refs\Branches $branches */
            $branches = $this->getApiClient()->api('Repositories\Refs\Branches');
            $featureInfo = $branches->get($username, $repository, $featureName);
            $featureInfo = GuzzleHttp\json_decode($featureInfo->getContent(), true);
        } catch (\Exception $e) { // TODO Verify right exception
            $featureInfo = [];
        }

        $feature = new Feature($featureName);

        if (empty($featureInfo)) {
            $feature->setStatus(Feature::STATUS_NEW);
        } else {
            $feature->setStatus(Feature::STATUS_STARTED)
                    ->setCommit($featureInfo['target']['hash']);

            $mergeRequest = $this->getMergeRequestByFeature($feature);

            if ($mergeRequest && $mergeRequest->getNumber()) {
                $feature->setMergeRequest($mergeRequest);

                $labels = $this->getFeatureLabels($feature);
                $feature->setLabels($labels);

                if (in_array($this->getConfiguration()->getLabelForReleaseCandidate(), $feature->getLabels())) {
                    $feature->setStatus(Feature::STATUS_RELEASE_CANDIDATE);
                }

                if (in_array($this->getConfiguration()->getLabelForReleaseStable(), $feature->getLabels())) {
                    $feature->setStatus(Feature::STATUS_RELEASE_STABLE);
                }
            }
        }

        return $feature;
    }

    public function addLabelToFeature(Feature $feature, $label)
    {
        $username = $this->getConfiguration()->getUsername();
        $repository = $this->getConfiguration()->getRepository();

        /** @var \Bitbucket\API\Repositories\PullRequests $mergeRequestsApi */
        $mergeRequestsApi = $this->getApiClient()->api('Repositories\PullRequests');

        /** @var MergeRequest $featureMergeRequest */
        $featureMergeRequest = $feature->getMergeRequest();

        $mergeRequestsApi->update($username, $repository, $featureMergeRequest->getNumber(), [
            'title'       => "{{$label}} " . $featureMergeRequest->getName(),
            'destination' => ['branch' => ['name' => $featureMergeRequest->getTargetBranch()]]
        ]);

        $feature->addLabel($label);
    }

    public function removeLabelsFromFeature(Feature $feature)
    {
        $username = $this->getConfiguration()->getUsername();
        $repository = $this->getConfiguration()->getRepository();

        /** @var \Bitbucket\API\Repositories\PullRequests $mergeRequestsApi */
        $mergeRequestsApi = $this->getApiClient()->api('Repositories\PullRequests');

        $labels = [
            $this->getConfiguration()->getLabelForReleaseCandidate(),
            $this->getConfiguration()->getLabelForReleaseStable(),
        ];

        array_walk($labels, function($label) use ($feature) {
            $name = str_replace("{{$label}}", '', $feature->getMergeRequest()->getName());
            $feature->getMergeRequest()->setName(trim($name));
        });

        $mergeRequestsApi->update($username, $repository, $feature->getMergeRequest()->getNumber(), [
            'title'       => $feature->getMergeRequest()->getName(),
            'destination' => ['branch' => ['name' => $feature->getMergeRequest()->getTargetBranch()]]
        ]);

        $feature->setLabels([]);
    }

    /**
     * @param Release $release
     *
     * @return Release
     */
    public function startReleaseCandidate(Release $release)
    {
        $username     = $this->getConfiguration()->getUsername();
        $token        = $this->getConfiguration()->getToken();
        $repository   = $this->getConfiguration()->getRepository();
        $masterBranch = $this->getConfiguration()->getMasterBranch();

        $bitbucketApi = new \Bitbucket\API\Api(['base_url' => 'https://api.bitbucket.org/rest/api']);
        $bitbucketApi->getClient()->addListener(
            new \Bitbucket\API\Http\Listener\BasicAuthListener($username, $token)
        );
        /** @var \Bitbucket\API\Repositories\Refs\Branches $branchesApi */
        $branchesApi = $bitbucketApi->api('Repositories\Refs\Branches');
        $masterBranchInfo = GuzzleHttp\json_decode($branchesApi->get($username, $repository, $masterBranch)->getContent(), true);

        $branchCreated = $branchesApi->create(
            $username,
            $repository,
            $release->getVersion(),
            $masterBranchInfo['target']['hash']
        );

        $release->setStatus(Release::STATUS_STARTED);

        return $release;
    }

    public function pushFeatureIntoReleaseStable(Release $release, Feature $feature)
    {
        // TODO: Implement pushFeatureIntoRelease() method.
    }

    /**
     * @param Release $release
     *
     * @return Release
     */
    public function createReleaseTag(Release $release, $metadata = '')
    {
        // TODO: Implement createReleaseTag() method.
    }

    /**
     * @param Release $release
     * @param Feature $feature
     *
     * @return void
     */
    public function pushFeatureIntoReleaseCandidate(Release $release, Feature $feature)
    {
        // TODO: Implement pushFeatureIntoReleaseCandidate() method.
    }

    /**
     * @param Feature $feature
     *
     * @return Feature
     */
    public function closeFeature(Feature $feature)
    {
        // TODO: Implement closeFeature() method.
    }

    /**
     * @param Feature $feature
     *
     * @return MergeRequest|null
     */
    public function getMergeRequestByFeature(Feature $feature)
    {
        $username     = $this->getConfiguration()->getUsername();
        $repository   = $this->getConfiguration()->getRepository();
        $masterBranch = $this->getConfiguration()->getMasterBranch();

        /** @var \Bitbucket\API\Repositories\PullRequests $mergeRequestsApi */
        $mergeRequestsApi = $this->getApiClient()->api('Repositories\PullRequests');
        $mergeRequests = $mergeRequestsApi->all($username, $repository, ['state' => 'OPEN'])->getContent();
        $mergeRequests = GuzzleHttp\json_decode($mergeRequests, true);

        foreach ($mergeRequests['values'] as $mergeRequestInfo) {
            if ($mergeRequestInfo['source']['branch']['name'] === $feature->getName()
                && $mergeRequestInfo['destination']['branch']['name'] === $masterBranch
            ) {
                $diff = $mergeRequestsApi->diff($username, $repository,  $mergeRequestInfo['id'])->getContent();
                $diffFiles = explode("diff --git ", $diff);

                // Dirty hack to detect if pull request is mergeable
                $isMergeable = true;
                foreach ($diffFiles as $diffFile) {
                    if (strpos($diffFile, '<<<<<<< destination') !== false
                        && strpos($diffFile, '>>>>>>> source') !== false
                    ) {
                        $isMergeable = false;
                        break;
                    }
                }

                $mergeRequest = new MergeRequest($mergeRequestInfo['id']);
                $mergeRequest->setName($mergeRequestInfo['title'])
                    ->setCommit($mergeRequestInfo['source']['commit']['hash'])
                    ->setSourceBranch($mergeRequestInfo['source']['branch']['name'])
                    ->setUrl($mergeRequestInfo['links']['html']['href'])
                    ->setTargetBranch($mergeRequestInfo['destination']['branch']['name'])
                    ->setDescription($mergeRequestInfo['description'])
                    ->setIsMergeable($isMergeable);

                print_r($mergeRequest);

                return $mergeRequest;
            }
        }
        return null;
    }

    /**
     * @param Feature $feature
     *
     * @return Feature
     */
    public function markFeatureReadyForReleaseCandidate(Feature $feature)
    {
        if (!$feature->getMergeRequest()) {
            $mergeRequest = $this->openMergeRequestByFeature($feature);
            $feature->setMergeRequest($mergeRequest);
        }

        return parent::markFeatureReadyForReleaseCandidate($feature);
    }

    /**
     * @param Feature $feature
     *
     * @return MergeRequest
     */
    public function openMergeRequestByFeature(Feature $feature)
    {
        $username     = $this->getConfiguration()->getUsername();
        $repository   = $this->getConfiguration()->getRepository();
        $masterBranch = $this->getConfiguration()->getMasterBranch();

        /** @var \Bitbucket\API\Repositories\PullRequests $mergeRequestsApi */
        $mergeRequestsApi = $this->getApiClient()->api('Repositories\PullRequests');

        $mergeRequestInfo = $mergeRequestsApi->create($username, $repository, array(
            'title'         => ucfirst(str_replace('_', ' ', $feature->getName())),
            'source'        => array(
                'branch'    => array(
                    'name'  => $feature->getName()
                ),
                'repository' => array(
                    'full_name' => "{$username}/{$repository}"
                )
            ),
            'destination'   => array(
                'branch'    => array(
                    'name'  => $masterBranch
                )
            )
        ))->getContent();

        $mergeRequestInfo = GuzzleHttp\json_decode($mergeRequestInfo, true);

        $pullRequestCommits = $mergeRequestsApi->commits($username, $repository, $mergeRequestInfo['id'])->getContent();
        $pullRequestCommits = GuzzleHttp\json_decode($pullRequestCommits, true);

        $pullRequestDescription = array_reduce($pullRequestCommits['values'], function ($message, $commit) {
            return $message . '* ' . $commit['message'] . PHP_EOL;
        }, '');

        $mergeRequestsApi->update(
            $username,
            $repository,
            $mergeRequestInfo['id'],
            [
                'title' => $mergeRequestInfo['title'],
                'description' => $pullRequestDescription,
                'destination' => ['branch' => ['name' => $masterBranch]],
            ]
        );

        return new MergeRequest($mergeRequestInfo['id']);
    }

    public function getLatestReleaseStableTag()
    {
        // TODO: Implement getLatestReleaseTag() method.
    }

    public function getLatestReleaseCandidateTag()
    {
        // TODO: Implement getLatestTestReleaseTag() method.
    }

    /**
     * @param Feature $feature
     *
     * @return Feature
     */
    public function startFeature(Feature $feature)
    {
        // TODO: Implement startFeature() method.
    }

    /**
     * @return Client
     */
    protected function getApiClient()
    {
        if (empty($this->apiClient)) {
            $username = $this->getConfiguration()->getUsername();
            $token    = $this->getConfiguration()->getToken();

            $bitbucket = new \Bitbucket\API\Api();
            $bitbucket->getClient()->addListener(
                new \Bitbucket\API\Http\Listener\BasicAuthListener($username, $token)
            );
            $this->apiClient = $bitbucket;
        }

        return $this->apiClient;
    }

    public function getFeatureLabels(Feature $feature)
    {
        if ($feature->getMergeRequest()) {
            preg_match('/(\{[A-Z-]*\})/', $feature->getMergeRequest()->getName(), $matches);
            $labels = array_map(function ($label) {
                return str_replace(['{', '}'], '', $label);
            }, $matches);

            return (empty($labels)) ? [] : $labels;
        }

        return array();
    }

    protected function getLatestVersion()
    {
        $username   = $this->getConfiguration()->getUsername();
        $repository = $this->getConfiguration()->getRepository();
        $client     = $this->getApiClient();

        // get Tags
        $versionsTags = array();
        /** @var \Bitbucket\API\Repositories\Refs\Tags $tagsApi */
        $tagsApi = $client->api('Repositories\Refs\Tags');
        $tags = GuzzleHttp\json_decode($tagsApi->all($username, $repository)->getContent(), true);

        foreach ($tags['values'] as $tagInfo) {
            try {
                Version::fromString($tagInfo['name']);
                array_push($versionsBranches, $tagInfo['name']);
            } catch (InvalidArgumentException $e) {
                continue;
            }
        }

        // get Branches
        $versionsBranches = array();
        /** @var \Bitbucket\API\Repositories\Refs\Branches $branchesApi */
        $branchesApi = $client->api('Repositories\Refs\Branches');
        $branches = GuzzleHttp\json_decode($branchesApi->all($username, $repository)->getContent(), true);

        foreach ($branches['values'] as $branchInfo) {
            try {
                Version::fromString($branchInfo['name']);
                array_push($versionsBranches, $branchInfo['name']);
            } catch (InvalidArgumentException $e) {
                continue;
            }
        }

        $versions = array_merge($versionsTags, $versionsBranches);
        $version  = (empty($versions)) ? Configuration::DEFAULT_VERSION : end(Semver::sort($versions));

        return Version::fromString($version);
    }

    /**
     * @param Release $release
     *
     * @return void
     */
    public function removeReleaseCandidates(Release $release)
    {
        // TODO: Implement removeReleaseCandidates() method.
    }

    /**
     * @param Feature $feature
     * @param Release $release
     *
     * @return bool
     */
    public function isFeatureReadyForRelease(Feature $feature, Release $release)
    {
        // TODO: Implement isFeatureReadyForRelease() method.
    }
}