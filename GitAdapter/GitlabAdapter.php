<?php

namespace Mirocode\GitReleaseMan\GitAdapter;

use Composer\Semver\Semver;
use Gitlab\Api\MergeRequests;
use Gitlab\Api\Projects;
use Gitlab\Api\Repositories;
use Gitlab\Client;
use Gitlab\Exception\RuntimeException as GitlabRuntimeException;
use Gitlab\ResultPager;
use InvalidArgumentException;
use Mirocode\GitReleaseMan\Entity\Feature;
use Mirocode\GitReleaseMan\Entity\MergeRequest;
use Mirocode\GitReleaseMan\Entity\Release;
use Mirocode\GitReleaseMan\ExitException;
use Mirocode\GitReleaseMan\GitAdapter\GitAdapterAbstract as GitAdapterAbstract;
use Mirocode\GitReleaseMan\GitAdapter\GitAdapterInterface as GitAdapterInterface;
use Mirocode\GitReleaseMan\Configuration;
use Mirocode\GitReleaseMan\Version;

class GitlabAdapter extends GitAdapterAbstract implements GitAdapterInterface, GitServiceInterface
{
    const ADAPTER_NAME = 'gitlab';

    /**
     * @var Client
     */
    protected $apiClient;

    /**
     * @return Feature[]
     */
    public function getFeaturesList()
    {
        $repository = $this->getConfiguration()->getRepository();
        $pager      = new ResultPager($this->getApiClient());

        /** @var Repositories $repository */
        $repositoryApi = $this->getApiClient()->api('repositories');
        $branches      = $pager->fetchall($repositoryApi, 'branches', [$repository]);

        $features = array_map(function ($branch) {
            return $this->buildFeature($branch['name']);
        }, $branches);

        $features = array_filter($features, function (Feature $feature) {
            return (strpos($feature->getName(), $this->getConfiguration()->getFeaturePrefix()) === 0);
        });

        return $features;
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

        $repository   = $this->getConfiguration()->getRepository();
        $masterBranch = $this->getConfiguration()->getMasterBranch();

        /** @var Repositories $repositoryApi */
        $repositoryApi = $this->getApiClient()->api('repositories');
        $branchInfo    = $repositoryApi->createBranch($repository, $feature->getName(), $masterBranch);

        $feature->setStatus(Feature::STATUS_STARTED)
                ->setCommit($branchInfo['commit']['id']);

        return $feature;
    }

    /**
     * @param Release $release
     *
     * @return Release
     */
    public function startReleaseCandidate(Release $release)
    {
        $repository   = $this->getConfiguration()->getRepository();
        $masterBranch = $this->getConfiguration()->getMasterBranch();

        /** @var Repositories $repositoryApi */
        $repositoryApi = $this->getApiClient()->api('repositories');
        $repositoryApi->createBranch($repository, $release->getBranch(), $masterBranch);

        $release->setStatus(Release::STATUS_STARTED);

        return $release;
    }

    /**
     * @param Feature $feature
     * @param         $label
     *
     *
     * @return Feature
     */
    public function addLabelToFeature(Feature $feature, $label)
    {
        $repository = $this->getConfiguration()->getRepository();

        /** @var MergeRequests $mergeRequestsApi */
        $mergeRequestsApi = $this->getApiClient()->api('merge_requests');
        $mergeRequestsApi
            ->update($repository, $feature->getMergeRequest()->getNumber(),
                ['labels' => implode(',', $feature->getLabels()) . ',' . $label]);

        $feature->addLabel($label);

        return $feature;
    }

    /**
     * @param Feature $feature
     *
     * @return array
     */
    public function getFeatureLabels(Feature $feature)
    {
        if (!$feature->getMergeRequest()->getNumber()) {
            return [];
        }

        $repository   = $this->getConfiguration()->getRepository();
        $masterBranch = $this->getConfiguration()->getMasterBranch();

        /** @var MergeRequests $mergeRequestsApi */
        $mergeRequestsApi = $this->getApiClient()->api('merge_requests');
        $mergeRequests    = $mergeRequestsApi->all($repository, [
            'state'         => MergeRequests::STATE_OPENED,
            'source_branch' => $feature->getName(),
            'target_branch' => $masterBranch,
        ]);

        if (empty($mergeRequests)) {
            return [];
        }

        $mergeRequests = array_filter($mergeRequests, function ($mergeRequestInfo) use ($masterBranch, $feature) {
            return ($mergeRequestInfo['state'] === MergeRequests::STATE_OPENED
                && $mergeRequestInfo['target_branch'] === $masterBranch
                && $mergeRequestInfo['source_branch'] === $feature->getName());
        });

        return (count($mergeRequests) === 1) ? $mergeRequests[0]['labels'] : [];
    }

    /**
     * @param Feature $feature
     *
     * @return MergeRequest
     */
    public function getMergeRequestByFeature(Feature $feature)
    {
        $repository   = $this->getConfiguration()->getRepository();
        $masterBranch = $this->getConfiguration()->getMasterBranch();

        /** @var MergeRequests $mergeRequestsApi */
        $mergeRequestsApi = $this->getApiClient()->api('merge_requests');
        $mergeRequests    = $mergeRequestsApi->all($repository, [
            'state'         => MergeRequests::STATE_OPENED,
            'source_branch' => $feature->getName(),
            'target_branch' => $masterBranch,
        ]);

        if (empty($mergeRequests)) {
            return null;
        }

        $mergeRequests = array_filter($mergeRequests, function ($mergeRequestInfo) use ($masterBranch, $feature) {
            return ($mergeRequestInfo['state'] === MergeRequests::STATE_OPENED
                && $mergeRequestInfo['target_branch'] === $masterBranch
                && $mergeRequestInfo['source_branch'] === $feature->getName());
        });

        return (count($mergeRequests) === 1) ? $this->buildMergeRequest($mergeRequests[0]['iid']) : null;
    }

    /**
     * @param $feature
     *
     * @return MergeRequest
     */
    public function openMergeRequestByFeature(Feature $feature)
    {
        $repository   = $this->getConfiguration()->getRepository();
        $masterBranch = $this->getConfiguration()->getMasterBranch();

        // We need project id becase of the API issue https://gitlab.com/gitlab-org/gitlab-ce/issues/41675
        /** @var Projects $projectsApi */
        $projectsApi = $this->getApiClient()->api('projects');
        $projectInfo = $projectsApi->show($repository);

        /** @var MergeRequests $mergeRequestsApi */
        $mergeRequestsApi = $this->getApiClient()->api('merge_requests');
        $mergeRequest     = $mergeRequestsApi
            ->create($repository, $feature->getName(), $masterBranch, $feature->getName(), null, $projectInfo['id']);

        $mergeRequestCommits = $mergeRequestsApi->commits($projectInfo['id'], $mergeRequest['iid']);

        $mergeRequestDescription = array_reduce($mergeRequestCommits, function ($message, $commit) {
            return $message . '* ' . $commit['message'] . ' | ' . $commit['message'] . PHP_EOL;
        }, '');

        $mergeRequestsApi->update($repository, $mergeRequest['iid'], ['description' => $mergeRequestDescription]);

        return $this->buildMergeRequest($mergeRequest['iid']);
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
     * @return Version
     */
    protected function getLatestVersion()
    {
        $repository = $this->getConfiguration()->getRepository();

        $pager = new ResultPager($this->getApiClient());

        /** @var Repositories $repository */
        $repositoryApi = $this->getApiClient()->api('repositories');
        $tags          = $pager->fetchall($repositoryApi, 'tags', [$repository]);
        $branches      = $pager->fetchall($repositoryApi, 'branches', [$repository]);

        $versions = array_filter(array_merge($tags, $branches), function ($versionInfo) {
            try {
                Version::fromString($versionInfo['name']);

                return true;
            } catch (InvalidArgumentException $e) {
                return false;
            }
        });

        $versions = Semver::sort(array_map(function ($versionInfo) {
            return $versionInfo['name'];
        }, $versions));

        $version = (empty($versions)) ? Configuration::DEFAULT_VERSION : end($versions);

        return Version::fromString($version);
    }

    /**
     * There is no possibility to merge branches directly, so we use Merge request to push feature into release
     *
     * @param Release $release
     * @param Feature $feature
     *
     */
    public function pushFeatureIntoReleaseCandidate(Release $release, Feature $feature)
    {
        $repository = $this->getConfiguration()->getRepository();

        // We need project id becase of the API issue https://gitlab.com/gitlab-org/gitlab-ce/issues/41675
        /** @var Projects $projectsApi */
        $projectsApi = $this->getApiClient()->api('projects');
        $projectInfo = $projectsApi->show($repository);

        /** @var MergeRequests $mergeRequestsApi */
        $mergeRequestsApi = $this->getApiClient()->api('merge_requests');
        $mergeRequest     = $mergeRequestsApi
            ->create(
                $repository,
                $feature->getName(),
                $release->getBranch(),
                $feature->getName() . ' Release: ' . $release->getVersion(),
                null,
                $projectInfo['id']
            );
        $mergeRequestsApi->merge($projectInfo['id'], $mergeRequest['iid']);

        $release->addFeature($feature);
    }

    /**
     * @param Release $release
     * @param Feature $feature
     */
    public function pushFeatureIntoReleaseStable(Release $release, Feature $feature)
    {
        $repository = $this->getConfiguration()->getRepository();

        // We need project id becase of the API issue https://gitlab.com/gitlab-org/gitlab-ce/issues/41675
        /** @var Projects $projectsApi */
        $projectsApi = $this->getApiClient()->api('projects');
        $projectInfo = $projectsApi->show($repository);

        /** @var MergeRequests $mergeRequestsApi */
        $mergeRequestsApi = $this->getApiClient()->api('merge_requests');
        $mergeRequestsApi->merge($projectInfo['id'], $feature->getMergeRequest()->getNumber());

        $release->addFeature($feature);
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
     * @param Release $release
     *
     * @return Release
     */
    public function createReleaseTag(Release $release)
    {
        $repository = $this->getConfiguration()->getRepository();

        /** @var Repositories $repositoryApi */
        $repositoryApi = $this->getApiClient()->api('repositories');
        $repositoryApi->createTag($repository, $release->getVersion(), $release->getBranch());

        return $release;
    }

    /**
     * @param Release $release
     *
     * @return array
     */
    public function getRCBranchesListByRelease(Release $release)
    {
        $repository = $this->getConfiguration()->getRepository();
        $pager      = new ResultPager($this->getApiClient());

        /** @var Repositories $repositoryApi */
        $repositoryApi = $this->getApiClient()->api('repositories');
        $branches      = $pager->fetchall($repositoryApi, 'branches', [$repository]);

        $branches = array_map(function ($branch) {
            return $branch['name'];
        }, $branches);

        return array_filter($branches, function ($branch) use ($release) {
            return (strpos($branch, $release->getVersion() . '-RC') === 0);
        });
    }

    /**
     * @return string
     * @throws ExitException
     */
    public function getLatestReleaseStableTag()
    {
        $repository = $this->getConfiguration()->getRepository();
        $pager      = new ResultPager($this->getApiClient());
        /** @var Repositories $repositoryApi */
        $repositoryApi = $this->getApiClient()->api('repositories');

        $versionsTags = array_map(function ($branch) {
            return $branch['name'];
        }, $pager->fetchall($repositoryApi, 'tags', [$repository]));

        $versionsTags = array_filter($versionsTags, function ($version) {
            try {
                return Version::fromString($version)->isStable();
            } catch (InvalidArgumentException $e) {
                return false;
            }
        });

        if (empty($versionsTags)) {
            throw new ExitException("There is no any stable release tag");
        }

        $versionsTags = Semver::sort($versionsTags);
        return end($versionsTags);
    }

    /**
     * @return string
     * @throws ExitException
     */
    public function getLatestReleaseCandidateTag()
    {
        $repository = $this->getConfiguration()->getRepository();
        $pager      = new ResultPager($this->getApiClient());
        /** @var Repositories $repositoryApi */
        $repositoryApi = $this->getApiClient()->api('repositories');

        $versionsTags = array_map(function ($branch) {
            return $branch['name'];
        }, $pager->fetchall($repositoryApi, 'tags', [$repository]));

        $versionsTags = array_filter($versionsTags, function ($version) {
            try {
                Version::fromString($version);

                return true;
            } catch (InvalidArgumentException $e) {
                return false;
            }
        });

        if (empty($versionsTags)) {
            throw new ExitException("There is no any release candidate tag");
        }
        $versionsTags              = Semver::sort($versionsTags);
        $latestReleaseCandidateTag = end($versionsTags);

        if (Version::fromString($latestReleaseCandidateTag)->isStable()) {
            throw new ExitException("Latest tag {$latestReleaseCandidateTag} is Stable. Generate RC.");
        }

        return $latestReleaseCandidateTag;
    }

    /**
     * @param Feature $feature
     *
     * @return Feature
     */
    public function closeFeature(Feature $feature)
    {
        $repository = $this->getConfiguration()->getRepository();

        // We need project id becase of the API issue https://gitlab.com/gitlab-org/gitlab-ce/issues/41675
        /** @var Projects $projectsApi */
        $projectsApi = $this->getApiClient()->api('projects');
        $projectInfo = $projectsApi->show($repository);

        /** @var Repositories $repositoryApi */
        $repositoryApi = $this->getApiClient()->api('repositories');
        $repositoryApi->deleteBranch($projectInfo['id'], $feature->getName());

        $feature->setStatus(Feature::STATUS_CLOSED);

        return $feature;
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
        $repository = $this->getConfiguration()->getRepository();

        /** @var Repositories $repositoryApi */
        $repositoryApi = $this->getApiClient()->api('repositories');
        try {
            $featureInfo = $repositoryApi->branch($repository, $featureName);
        } catch (GitlabRuntimeException $e) {
            $featureInfo = null;
        }

        $feature = new Feature($featureName);

        if (empty($featureInfo)) {
            $feature->setStatus(Feature::STATUS_NEW);
        } else {
            $feature->setStatus(Feature::STATUS_STARTED)
                    ->setCommit($featureInfo['commit']['id']);

            $mergeRequest = $this->getMergeRequestByFeature($feature);

            if ($mergeRequest && $mergeRequest->getNumber()) {
                $feature->setMergeRequest($mergeRequest);

                $feature->setLabels($this->getFeatureLabels($feature));

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

    /**
     * @return Client
     */
    protected function getApiClient()
    {
        if (empty($this->apiClient)) {
            $client = Client::create($this->getConfiguration()->getGitAdapterEndpoint())
                            ->authenticate($this->getConfiguration()->getToken(), Client::AUTH_URL_TOKEN);

            $this->apiClient = $client;
        }

        return $this->apiClient;
    }

    /**
     * @param Feature $feature
     */
    public function removeLabelsFromFeature(Feature $feature)
    {
        if ($feature->getMergeRequest()) {
            $repository = $this->getConfiguration()->getRepository();

            $labels = [
                $this->getConfiguration()->getLabelForReleaseStable(),
                $this->getConfiguration()->getLabelForReleaseCandidate(),
            ]; // TODO remove only git-release-man labels

            // We need project id becase of the API issue https://gitlab.com/gitlab-org/gitlab-ce/issues/41675
            /** @var Projects $projectsApi */
            $projectsApi = $this->getApiClient()->api('projects');
            $projectInfo = $projectsApi->show($repository);

            /** @var MergeRequests $mergeRequestsApi */
            $mergeRequestsApi = $this->getApiClient()->api('merge_requests');
            $mergeRequestsApi->update(
                $projectInfo['id'],
                $feature->getMergeRequest()->getNumber(),
                ['labels' => '']
            );
        }
    }

    /**
     * @param Release $release
     */
    public function removeReleaseCandidates(Release $release)
    {
        $repository = $this->getConfiguration()->getRepository();

        // We need project id becase of the API issue https://gitlab.com/gitlab-org/gitlab-ce/issues/41675
        /** @var Projects $projectsApi */
        $projectsApi = $this->getApiClient()->api('projects');
        $projectInfo = $projectsApi->show($repository);

        /** @var Repositories $repositoryApi */
        $repositoryApi = $this->getApiClient()->api('repositories');

        foreach ($this->getRCBranchesListByRelease($release) as $releaseCandidateBranch) {
            $repositoryApi->deleteBranch($projectInfo['id'], $releaseCandidateBranch);
        }
    }

    /**
     * @param integer $mergeRequestId
     *
     * @return MergeRequest
     */
    public function buildMergeRequest($mergeRequestId)
    {
        $repository = $this->getConfiguration()->getRepository();

        /** @var MergeRequests $mergeRequestsApi */
        $mergeRequestsApi = $this->getApiClient()->api('merge_requests');
        $mergeRequestInfo = $mergeRequestsApi->show($repository, $mergeRequestId);

        $mergeRequest = new MergeRequest($mergeRequestInfo['iid']);
        $mergeRequest->setName($mergeRequestInfo['title'])
                     ->setUrl($mergeRequestInfo['web_url'])
                     ->setDescription($mergeRequestInfo['description'])
                     ->setIsMergeable(($mergeRequestInfo['merge_status'] === 'can_be_merged'))
                     ->setCommit($mergeRequestInfo['sha'])
                     ->setSourceBranch($mergeRequestInfo['source_branch'])
                     ->setTargetBranch($mergeRequestInfo['target_branch']);

        return $mergeRequest;
    }
}