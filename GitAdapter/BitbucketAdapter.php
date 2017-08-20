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

class BitbucketAdapter extends GitremoteAdapter implements GitAdapterInterface
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

        $branches = json_decode($branchesApi->all($username, $repository)->getContent(), true);
        $branchesInfo = $branches['values'];

        if (empty($branchesInfo)) {
            return array();
        }

        $branchesNames = array_map(function ($branch) {
            return $branch['name'];
        }, $branchesInfo);

        $features = array_filter($branchesNames, function ($branch) {
            return (strpos($branch, 'feature') === 0);
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
            $featureInfo = json_decode($featureInfo->getContent(), true);
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
                $feature->setMergeRequestNumber($mergeRequest->getNumber())
                        ->setMergeRequest($mergeRequest);

                $labels = $this->getFeatureLabels($feature);
                $feature->setLabels($labels);

                if (in_array($this->getConfiguration()->getLabelForTest(), $feature->getLabels())) {
                    $feature->setStatus(Feature::STATUS_TEST);
                }

                if (in_array($this->getConfiguration()->getLabelForRelease(), $feature->getLabels())) {
                    $feature->setStatus(Feature::STATUS_RELEASE);
                }
            }
        }

        return $feature;
    }

    public function removeReleaseCandidates($release)
    {
        // TODO: Implement removeReleaseCandidates() method.
    }

    public function addLabelToFeature(Feature $feature, $label)
    {
        $username = $this->getConfiguration()->getUsername();
        $repository = $this->getConfiguration()->getRepository();

        /** @var \Bitbucket\API\Repositories\PullRequests $mergeRequestsApi */
        $mergeRequestsApi = $this->getApiClient()->api('Repositories\PullRequests');

        $mergeRequestsApi->update($username, $repository, $feature->getMergeRequestNumber(), [
            'title'       => "{{$label}} " . $feature->getMergeRequest()->getName(),
            'destination' => ['branch' => ['name' => $feature->getMergeRequest()->getTargetBranch()]]
        ]);

        $feature->addLabel($label);
    }

    public function removeLabelsFromFeature(Feature $feature)
    {
        // TODO: Implement removeLabelsFromFeature() method.
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
        $masterBranchInfo = json_decode($branchesApi->get($username, $repository, $masterBranch)->getContent(), true);

        $branchCreated = $branchesApi->create(
            $username,
            $repository,
            [
                'name' => $release->getVersion(),
                'startPoint' => $masterBranchInfo['target']['hash'],
                'message' => 'create new release',
            ]
        );
        print_r($branchCreated);

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
        $mergeRequests = json_decode($mergeRequests, true);


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

                return $mergeRequest;
            }
        }
        return null;
    }

    /**
     * @param Feature $feature
     *
     * @return MergeRequest
     */
    public function openMergeRequestByFeature(Feature $feature)
    {
        // TODO: Implement openMergeRequestByFeature() method.
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
        $tags = json_decode($tagsApi->all($username, $repository)->getContent(), true);

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
        $branches = json_decode($branchesApi->all($username, $repository)->getContent(), true);

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
}