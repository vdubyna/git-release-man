<?php

namespace Mirocode\GitReleaseMan\GitAdapter;

use Composer\Semver\Semver;
use Gitlab\Api\MergeRequests;
use Gitlab\Api\Repositories;
use Gitlab\Client;
use Gitlab\Exception\RuntimeException as GitlabRuntimeException;
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
    /**
     * @var Client
     */
    protected $apiClient;


    /**
     * @return Feature[]
     */
    public function getFeaturesList()
    {
        $repository   = $this->getConfiguration()->getRepository();
        $pager = new \Gitlab\ResultPager($this->getApiClient());

        /** @var Repositories $repository */
        $repositoryApi = $this->getApiClient()->api('repositories');
        $branches = $pager->fetchall($repositoryApi, 'branches', [$repository]);

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
        $branchInfo = $repositoryApi->createBranch($repository, $feature->getName(), $masterBranch);

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
        $username     = $this->getConfiguration()->getUsername();
        $repository   = $this->getConfiguration()->getRepository();
        $masterBranch = $this->getConfiguration()->getMasterBranch();

        $masterBranchInfo = $this->getApiClient()
                                 ->gitData()
                                 ->references()
                                 ->show($username, $repository, "heads/{$masterBranch}");

        $this->getApiClient()
             ->gitData()
             ->references()
             ->create($username, $repository, array(
                 'ref' => "refs/heads/{$release->getBranch()}",
                 'sha' => $masterBranchInfo['object']['sha'],
             ));

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
        $client     = $this->getApiClient();
        $repository = $this->getConfiguration()->getRepository();
        $username   = $this->getConfiguration()->getUsername();

        $client->issues()
               ->labels()
               ->add($username, $repository, $feature->getMergeRequest()->getNumber(), $label);
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

        if ($feature->getMergeRequest()->getNumber()) {
            $repository   = $this->getConfiguration()->getRepository();
            $username     = $this->getConfiguration()->getUsername();
            $masterBranch = $this->getConfiguration()->getMasterBranch();

            /** @var MergeRequests $mergeRequestsApi */
            $mergeRequestsApi = $this->getApiClient()->api('merge_requests');
            $mergeRequests = $mergeRequestsApi->all($repository, [
                'state' => MergeRequests::STATE_OPENED,
                'source_branch' => $feature->getName(),
                'target_branch' => $masterBranch
            ]);

            return array_map(function ($label) {
                return $label['name'];
            }, $mergeRequests[0]['labels']);
        }

        return [];
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
        $mergeRequests = $mergeRequestsApi->all($repository, [
            'state' => MergeRequests::STATE_OPENED,
            'source_branch' => $feature->getName(),
            'target_branch' => $masterBranch
            ]);

        if (count($mergeRequests) === 1) {
            return $this->buildMergeRequest($mergeRequests[0]['id']);
        }

        return null;
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

        /** @var MergeRequests $mergeRequestsApi */
        $mergeRequestsApi = $this->getApiClient()->api('merge_requests');
        $mergeRequest = $mergeRequestsApi
            ->create($repository, $feature->getName(), $masterBranch, $feature->getName());

        $mergeRequestCommits = $mergeRequestsApi->commits($repository, $mergeRequest['id']);

        $mergeRequestDescription = array_reduce($mergeRequestCommits, function ($message, $commit) {
            return $message . '* ' . $commit['message'] . ' | ' . $commit['message'] . PHP_EOL;
        }, '');

        $mergeRequestsApi->update($repository, $mergeRequest['id'], ['description' => $mergeRequestDescription]);

        return $this->buildMergeRequest($mergeRequest['id']);
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
        $username   = $this->getConfiguration()->getUsername();
        $repository = $this->getConfiguration()->getRepository();
        $client     = $this->getApiClient();

        // get Tags
        $versionsTags = array();
        foreach ($client->repository()->tags($username, $repository) as $tag) {
            try {
                Version::fromString($tag['name']);
                array_push($versionsTags, $tag['name']);
            } catch (InvalidArgumentException $e) {
                continue;
            }
        }

        // get Branches
        $versionsBranches = array();
        foreach ($client->repository()->branches($username, $repository) as $branch) {
            try {
                Version::fromString($branch['name']);
                array_push($versionsBranches, $branch['name']);
            } catch (InvalidArgumentException $e) {
                continue;
            }
        }

        $versions = Semver::sort(array_merge($versionsTags, $versionsBranches));
        $version  = (empty($versions)) ? Configuration::DEFAULT_VERSION : end($versions);

        return Version::fromString($version);
    }

    /**
     * @param Release $release
     * @param Feature $feature
     *
     */
    public function pushFeatureIntoReleaseCandidate(Release $release, Feature $feature)
    {
        $client     = $this->getApiClient();
        $repository = $this->getConfiguration()->getRepository();
        $username   = $this->getConfiguration()->getUsername();
        $client->repository()->merge($username, $repository, $release->getVersion(), $feature->getName());
        $release->addFeature($feature);
    }

    /**
     * @param Release $release
     * @param Feature $feature
     */
    public function pushFeatureIntoReleaseStable(Release $release, Feature $feature)
    {
        $client     = $this->getApiClient();
        $repository = $this->getConfiguration()->getRepository();
        $username   = $this->getConfiguration()->getUsername();

        $client->pullRequest()->merge(
            $username,
            $repository,
            $feature->getMergeRequest()->getNumber(),
            "Add feature {$feature->getName()}",
            $feature->getMergeRequest()->getCommit(),
            'squash'
        );
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
        $client            = $this->getApiClient();
        $repository        = $this->getConfiguration()->getRepository();
        $username          = $this->getConfiguration()->getUsername();

        $releaseBranchInfo = $client
            ->repository()
            ->branches($username, $repository, $release->getBranch());
        $client->repository()
               ->releases()
               ->create($username, $repository,
                   array(
                       'tag_name'         => $release->getVersion(),
                       'name'             => $release->getVersion(),
                       'prerelease'       => (!$release->isStable()),
                       'target_commitish' => $releaseBranchInfo['commit']['sha'],
                   )
               );

        return $release;
    }

    /**
     * @param Release $release
     *
     * @return array
     */
    public function getRCBranchesListByRelease(Release $release)
    {
        $client     = $this->getApiClient();
        $repository = $this->getConfiguration()->getRepository();
        $username   = $this->getConfiguration()->getUsername();

        $branches = $client->repository()->branches($username, $repository);
        $branches = array_map(function ($branch) {
            return $branch['name'];
        }, $branches);

        return array_filter($branches, function ($branch) use ($release) {
            return (strpos($branch, $release->getVersion() . '-RC') === 0);
        });
    }

    public function getLatestReleaseStableTag()
    {
        $client     = $this->getApiClient();
        $repository = $this->getConfiguration()->getRepository();
        $username   = $this->getConfiguration()->getUsername();

        $latestRelease = $client->repository()->releases()->latest($username, $repository);

        return $latestRelease['tag_name'];
    }

    public function getLatestReleaseCandidateTag()
    {
        $client     = $this->getApiClient();
        $repository = $this->getConfiguration()->getRepository();
        $username   = $this->getConfiguration()->getUsername();

        $latestTestReleases = $client->repository()
                                     ->releases()
                                     ->all($username, $repository);
        $latestTestReleases = array_filter($latestTestReleases, function ($testRelease) {
            return ($testRelease['prerelease'] == true);
        });

        $latestTestRelease = array_shift($latestTestReleases);

        return $latestTestRelease['tag_name'];
    }

    /**
     * @param Feature $feature
     *
     * @return Feature
     */
    public function closeFeature(Feature $feature)
    {
        $username   = $this->getConfiguration()->getUsername();
        $repository = $this->getConfiguration()->getRepository();

        $this->getApiClient()
             ->gitData()
             ->references()
             ->remove($username, $repository, "heads/{$feature->getName()}");

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
        $repository  = $this->getConfiguration()->getRepository();

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
            $username   = $this->getConfiguration()->getUsername();
            $client     = $this->getApiClient();

            $labels = array(
                $this->getConfiguration()->getLabelForReleaseStable(),
                $this->getConfiguration()->getLabelForReleaseCandidate(),
            );

            foreach ($labels as $label) {
                $client->issues()
                       ->labels()
                       ->remove($username, $repository, $feature->getMergeRequest()->getNumber(), $label);
            }
        }
    }

    /**
     * @param Release $release
     */
    public function removeReleaseCandidates(Release $release)
    {
        $repository = $this->getConfiguration()->getRepository();
        $username   = $this->getConfiguration()->getUsername();
        $client     = $this->getApiClient();

        foreach ($this->getRCBranchesListByRelease($release) as $releaseCandidateBranch) {
            $client->gitData()
                   ->references()
                   ->remove($username, $repository, "heads/{$releaseCandidateBranch}");
        }
    }

    /**
     * @param integer $mergeRequestId
     *
     * @return MergeRequest
     */
    public function buildMergeRequest($mergeRequestId)
    {
        $repository   = $this->getConfiguration()->getRepository();
        $username     = $this->getConfiguration()->getUsername();

        /** @var MergeRequests $mergeRequestsApi */
        $mergeRequestsApi = $this->getApiClient()->api('merge_requests');
        $mergeRequestInfo = $mergeRequestsApi->show($repository, $mergeRequestId);

        $mergeRequest = new MergeRequest($mergeRequestInfo['id']);
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