<?php

namespace Mirocode\GitReleaseMan\GitAdapter;

use Bitbucket\API\Api as BitbucketApi;
use Bitbucket\API\Http\Listener\BasicAuthListener;
use Bitbucket\API\Http\Response\Pager;
use Composer\Semver\Semver;
use Gitlab\Api\MergeRequests;
use InvalidArgumentException;
use Bitbucket\API\Api as Client;
use Mirocode\GitReleaseMan\Entity\Feature;
use Mirocode\GitReleaseMan\Entity\MergeRequest;
use Mirocode\GitReleaseMan\Entity\Release;
use Mirocode\GitReleaseMan\ExitException;
use Mirocode\GitReleaseMan\GitAdapter\GitAdapterAbstract as GitAdapterAbstract;
use Mirocode\GitReleaseMan\GitAdapter\GitAdapterInterface as GitAdapterInterface;
use Mirocode\GitReleaseMan\Configuration;
use Mirocode\GitReleaseMan\Version;
use GuzzleHttp;

/**
 * Documentation https://gentlero.bitbucket.io/bitbucket-api/1.0/examples/#available-examples
 * API https://developer.atlassian.com/bitbucket/api/2/reference/resource/
 *
 *
 * Class BitbucketAdapter
 * @package Mirocode\GitReleaseMan\GitAdapter
 */
class BitbucketAdapter extends GitAdapterAbstract implements GitAdapterInterface, GitServiceInterface
{
    const ADAPTER_NAME = 'butbucket';

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

        $features = array_map(function ($branch) {
                return $this->buildFeature($branch['name']);
            },
            array_filter($branches['values'], function ($branch) {
                return (strpos($branch['name'], $this->getConfiguration()->getFeaturePrefix()) === 0);
            })
        );

        return ($features) ?: [];
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
        $username   = $this->getConfiguration()->getUsername();
        $repository = $this->getConfiguration()->getRepository();

        /** @var \Bitbucket\API\Repositories\Refs\Branches $branchesApi */
        $branchesApi = $this->getApiClient()->api('Repositories\Refs\Branches');

        try {
            $featureInfo = $branchesApi->get($username, $repository, $featureName)->getContent();
            $featureInfo = GuzzleHttp\json_decode($featureInfo, true);
        } catch (\Exception $e) { // TODO Verify right exception
            $featureInfo = [];
        }

        $feature = new Feature($featureName);

        if (empty($featureInfo) || !isset($featureInfo['target'])) { // TODO Error catcher
            $feature->setStatus(Feature::STATUS_NEW);
        } else {
            $feature->setStatus(Feature::STATUS_STARTED)
                    ->setCommit($featureInfo['target']['hash']); // TODO Verify if we need it

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
        $username   = $this->getConfiguration()->getUsername();
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
        $username   = $this->getConfiguration()->getUsername();
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
        $repository   = $this->getConfiguration()->getRepository();
        $masterBranch = $this->getConfiguration()->getMasterBranch();

        /** @var \Bitbucket\API\Repositories\Refs\Branches $branchesApi */
        $branchesApi = $this->getApiClient()->api('Repositories\Refs\Branches');
        $masterBranchInfo = GuzzleHttp\json_decode(
            $branchesApi->get($username, $repository, $masterBranch)->getContent(), true);

        $branchesApi->create(
            $username,
            $repository,
            $release->getVersion(),
            $masterBranchInfo['target']['hash']
        );

        $release->setStatus(Release::STATUS_STARTED);

        return $release;
    }

    /**
     * @param Release $release
     * @param Feature $feature
     *
     * @throws ExitException
     */
    public function pushFeatureIntoReleaseStable(Release $release, Feature $feature)
    {
        $username   = $this->getConfiguration()->getUsername();
        $repository = $this->getConfiguration()->getRepository();

        /** @var \Bitbucket\API\Repositories\PullRequests $mergeRequestsApi */
        $mergeRequestsApi = $this->getApiClient()->api('Repositories\PullRequests');

        $result = $mergeRequestsApi->accept(
            $username,
            $repository,
            $feature->getMergeRequest()->getNumber(),
            ['message' => 'Merge Pull Request']
        );
        $result = GuzzleHttp\json_decode($result->getContent(), true);

        if (isset($result['type']) && $result['type'] === 'error') {
            $mergeRequestsApi->decline($username, $repository, $feature->getMergeRequest()->getNumber());
            throw new ExitException(
                "Feature {$feature->getName()} can not be merged into Release {$release->getVersion()}");
        }

        $release->addFeature($feature);
    }

    /**
     * @param Release $release
     *
     * @param string  $metadata
     *
     * @return Release
     */
    public function createReleaseTag(Release $release, $metadata = '')
    {
        $username   = $this->getConfiguration()->getUsername();
        $repository = $this->getConfiguration()->getRepository();

        /** @var \Bitbucket\API\Repositories\Refs\Tags $tagsApi */
        $tagsApi = $this->getApiClient()->api('Repositories\Refs\Tags');

        /** @var \Bitbucket\API\Repositories\Refs\Branches $branchesApi */
        $branchesApi = $this->getApiClient()->api('Repositories\Refs\Branches');
        $releaseBranchInfo = GuzzleHttp\json_decode(
            $branchesApi->get($username, $repository, $release->getBranch())->getContent(), true);

        $tagsApi->create($username, $repository, $release->getVersion(), $releaseBranchInfo['target']['hash']);

        return $release;
    }

    /**
     * Open Merge request to release-candidate and merge it
     *
     * @param Release $release
     * @param Feature $feature
     *
     * @return void
     * @throws ExitException
     */
    public function pushFeatureIntoReleaseCandidate(Release $release, Feature $feature)
    {
        $username   = $this->getConfiguration()->getUsername();
        $repository = $this->getConfiguration()->getRepository();

        /** @var \Bitbucket\API\Repositories\PullRequests $mergeRequestsApi */
        $mergeRequestsApi = $this->getApiClient()->api('Repositories\PullRequests');

        $mergeRequestInfo = $mergeRequestsApi->create($username, $repository, [
            'title'       => ucfirst(str_replace('_', ' ', $feature->getName())), // TODO generate HumanReadable Title
            'source'      => [
                'branch'     => ['name'      => $feature->getName()],
                'repository' => ['full_name' => "{$username}/{$repository}"]
            ],
            'destination' => ['branch' => ['name'  => $release->getBranch()]]
        ]);

        $mergeRequestInfo = GuzzleHttp\json_decode($mergeRequestInfo->getContent(), true);
        $result = $mergeRequestsApi
            ->accept($username, $repository, $mergeRequestInfo['id'], ['message' => 'Merge feature']);
        $result = GuzzleHttp\json_decode($result->getContent(), true);

        if (isset($result['type']) && $result['type'] === 'error') {
            $mergeRequestsApi->decline($username, $repository, $mergeRequestInfo['id']);
            throw new ExitException(
                "Feature {$feature->getName()} can not be merged into Release {$release->getVersion()}");
        }

        $release->addFeature($feature);
    }

    /**
     * @param Feature $feature
     *
     * @return Feature
     */
    public function closeFeature(Feature $feature)
    {
        $this->getStyleHelper()
             ->note("This action 'closeFeature' is not supported by API see thread " .
                 "https://bitbucket.org/site/master/issues/12295/add-support-to-create-delete-branch-via");

        $feature->setStatus(Feature::STATUS_CLOSED);

        return $feature;
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
        $mergeRequests = $mergeRequestsApi->all($username, $repository, ['state' => 'OPEN']); // TODO Verify limit
        $mergeRequests = GuzzleHttp\json_decode($mergeRequests->getContent(), true);

        foreach ($mergeRequests['values'] as $mergeRequestInfo) {
            if ($mergeRequestInfo['source']['branch']['name'] === $feature->getName()
                && $mergeRequestInfo['destination']['branch']['name'] === $masterBranch
            ) {
                return $this->buildMergeRequest($mergeRequestInfo['number']);
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

        $mergeRequestInfo = $mergeRequestsApi->create($username, $repository, [
            'title'       => ucfirst(str_replace('_', ' ', $feature->getName())),
            'source'      => [
                'branch'     => ['name'      => $feature->getName()],
                'repository' => ['full_name' => "{$username}/{$repository}"]
            ],
            'destination' => ['branch' => ['name' => $masterBranch]]
        ]);

        $mergeRequestInfo = GuzzleHttp\json_decode($mergeRequestInfo->getContent(), true);

        $pullRequestCommits = $mergeRequestsApi->commits($username, $repository, $mergeRequestInfo['id']);
        $pullRequestCommits = GuzzleHttp\json_decode($pullRequestCommits->getContent(), true);

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

        return $this->buildMergeRequest($mergeRequestInfo['id']);
    }

    /**
     * @return string
     * @throws ExitException
     */
    public function getLatestReleaseStableTag()
    {
        $username   = $this->getConfiguration()->getUsername();
        $repository = $this->getConfiguration()->getRepository();

        /** @var \Bitbucket\API\Repositories\Refs\Tags $tagsApi */
        $tagsApi = $this->getApiClient()->api('Repositories\Refs\Tags');
        $page    = new Pager($tagsApi->getClient(), $tagsApi->all($username, $repository));

        $versionsTags = [];

        do {
            $tags = GuzzleHttp\json_decode($page->getCurrent()->getContent(), 1);

            foreach ($tags['values'] as $tagInfo) {
                try {
                    Version::fromString($tagInfo['name']);
                    array_push($versionsTags, $tagInfo['name']);
                } catch (InvalidArgumentException $e) {
                    continue;
                }
            }

            $page->fetchNext();
        } while(isset($tags['next']));

        $versionsTags = array_filter($versionsTags, function($version) {
            return Version::fromString($version)->isStable();
        });

        if (empty($versionsTags)) {
            throw new ExitException("There is no any stable release tag");
        }

        $versions = Semver::sort($versionsTags);
        $version  = end($versions);

        return Version::fromString($version)->getVersion();
    }

    /**
     * @return mixed|string
     * @throws ExitException
     */
    public function getLatestReleaseCandidateTag()
    {
        $username   = $this->getConfiguration()->getUsername();
        $repository = $this->getConfiguration()->getRepository();

        /** @var \Bitbucket\API\Repositories\Refs\Tags $tagsApi */
        $tagsApi = $this->getApiClient()->api('Repositories\Refs\Tags');
        $page    = new Pager($tagsApi->getClient(), $tagsApi->all($username, $repository));

        $versionsTags = [];

        do {
            $tags = GuzzleHttp\json_decode($page->getCurrent()->getContent(), 1);

            foreach ($tags['values'] as $tagInfo) {
                try {
                    Version::fromString($tagInfo['name']);
                    array_push($versionsTags, $tagInfo['name']);
                } catch (InvalidArgumentException $e) {
                    continue;
                }
            }

            $page->fetchNext();
        } while(isset($tags['next']));

        if (empty($versionsTags)) {
            throw new ExitException("There is no any release candidate tag");
        }

        $versions                   = Semver::sort($versionsTags);
        $latestReleaseCandidateTag  = end($versions);

        if (Version::fromString($latestReleaseCandidateTag)->isStable()) {
            throw new ExitException("Latest tag {$latestReleaseCandidateTag} is Stable. Generate RC.");
        }

        return $latestReleaseCandidateTag;
    }

    /**
     * @param Feature $feature
     *
     * @return Feature
     * @throws ExitException
     */
    public function startFeature(Feature $feature)
    {
        if ($feature->getStatus() !== Feature::STATUS_NEW) {
            throw new ExitException("You can start feature only if it has status: NEW.");
        }

        $username     = $this->getConfiguration()->getUsername();
        $repository   = $this->getConfiguration()->getRepository();
        $masterBranch = $this->getConfiguration()->getMasterBranch();

        /** @var \Bitbucket\API\Repositories\Refs\Branches $branchesApi */
        $branchesApi = $this->getApiClient()->api('Repositories\Refs\Branches');
        $masterBranchInfo = GuzzleHttp\json_decode(
            $branchesApi->get($username, $repository, $masterBranch)->getContent(), true);

        $branchesApi->create(
            $username,
            $repository,
            $feature->getName(),
            $masterBranchInfo['target']['hash']
        );

        $branchInfo = $branchesApi->get($username, $repository, $feature->getName());
        $branchInfo = GuzzleHttp\json_decode($branchInfo->getContent(), true);

        $feature->setStatus(Feature::STATUS_STARTED)
                ->setCommit($branchInfo['target']['hash']);

        return $feature;
    }

    /**
     * @return Client
     */
    protected function getApiClient()
    {
        if (empty($this->apiClient)) {
            $username = $this->getConfiguration()->getUsername();
            $token    = $this->getConfiguration()->getToken();

            $bitbucket = new BitbucketApi();
            $bitbucket->getClient()->addListener(
                new BasicAuthListener($username, $token)
            );
            $this->apiClient = $bitbucket;
        }

        return $this->apiClient;
    }

    /**
     *
     * @param Feature $feature
     *
     * @return array
     */
    public function getFeatureLabels(Feature $feature)
    {
        if ($feature->getMergeRequest()->getNumber()) {
            preg_match_all('/\{[A-Z-]*\}/', $feature->getMergeRequest()->getName(), $matches);
            if (!empty($matches)) {
                $labels = array_map(function ($label) {
                    return str_replace(['{', '}'], '', $label);
                }, $matches[0]);

                return (empty($labels)) ? [] : $labels;
            }
        }

        return [];
    }

    protected function getLatestVersion()
    {
        $username   = $this->getConfiguration()->getUsername();
        $repository = $this->getConfiguration()->getRepository();
        $client     = $this->getApiClient();

        // get Tags
        /** @var \Bitbucket\API\Repositories\Refs\Tags $tagsApi */
        $tagsApi = $client->api('Repositories\Refs\Tags');
        $page    = new Pager($tagsApi->getClient(), $tagsApi->all($username, $repository));

        $versionsTags = [];

        do {
            $tags = GuzzleHttp\json_decode($page->getCurrent()->getContent(), 1);

            foreach ($tags['values'] as $tagInfo) {
                try {
                    Version::fromString($tagInfo['name']);
                    array_push($versionsTags, $tagInfo['name']);
                } catch (InvalidArgumentException $e) {
                    continue;
                }
            }

            $page->fetchNext();
        } while(isset($tags['next']));

        // get Branches
        /** @var \Bitbucket\API\Repositories\Refs\Branches $branchesApi */
        $branchesApi = $client->api('Repositories\Refs\Branches');
        $page        = new Pager($branchesApi->getClient(), $branchesApi->all($username, $repository));

        $versionsBranches = [];

        do {
            $branches = GuzzleHttp\json_decode($page->getCurrent()->getContent(), 1);

            foreach ($branches['values'] as $branchInfo) {
                try {
                    Version::fromString($branchInfo['name']);
                    array_push($versionsBranches, $branchInfo['name']);
                } catch (InvalidArgumentException $e) {
                    continue;
                }
            }

            $page->fetchNext();
        } while(isset($branches['next']));

        $versions = Semver::sort(array_merge($versionsTags, $versionsBranches));
        $version  = (empty($versions)) ? Configuration::DEFAULT_VERSION : end($versions);

        return Version::fromString($version);
    }

    /**
     * @param Release $release
     *
     * @return void
     */
    public function removeReleaseCandidates(Release $release)
    {
        $this->getStyleHelper()
             ->note("This action 'removeReleaseCandidates' is not supported by API see thread " .
                 "https://bitbucket.org/site/master/issues/12295/add-support-to-create-delete-branch-via");
    }

    /**
     * @param Feature $feature
     * @param Release $release
     *
     * @return bool
     */
    public function isFeatureReadyForRelease(Feature $feature, Release $release)
    {
        return $feature->getMergeRequest()->getIsMergeable();
    }

    /**
     * @param integer $mergeRequestId
     *
     * @return MergeRequest
     */
    public function buildMergeRequest($mergeRequestId)
    {
        $username     = $this->getConfiguration()->getUsername();
        $repository   = $this->getConfiguration()->getRepository();

        /** @var \Bitbucket\API\Repositories\PullRequests $mergeRequestsApi */
        $mergeRequestsApi = $this->getApiClient()->api('Repositories\PullRequests');
        $mergeRequestInfo = $mergeRequestsApi->get($username, $repository, $mergeRequestId);
        $mergeRequestInfo = GuzzleHttp\json_decode($mergeRequestInfo->getContent(), true);

        $diff = $mergeRequestsApi->diff($username, $repository,  $mergeRequestInfo['id']);
        $diffFiles = explode("diff --git ", $diff->getContent());

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