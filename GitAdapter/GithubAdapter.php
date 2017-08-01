<?php

namespace Mirocode\GitReleaseMan\GitAdapter;

use Composer\Semver\Semver;
use Github\Client;
use InvalidArgumentException;
use Mirocode\GitReleaseMan\Entity\Feature;
use Mirocode\GitReleaseMan\Entity\MergeRequest;
use Mirocode\GitReleaseMan\GitAdapter\GitAdapterAbstract;
use Mirocode\GitReleaseMan\GitAdapter\GitAdapterInterface;
use Mirocode\GitReleaseMan\Configuration;
use Mirocode\GitReleaseMan\Version;

class GithubAdapter extends GitAdapterAbstract implements GitAdapterInterface
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
        $username   = $this->getConfiguration()->getUsername();
        $repository = $this->getConfiguration()->getRepository();

        $branches = $this->getApiClient()
                         ->repository()
                         ->branches($username, $repository);

        $features = array_map(function ($branch) {
            return $this->buildFeature($branch['name']);
        }, $branches);

        $features = array_filter($features, function (MergeRequest $feature) {
            return (strpos($feature->getName(), 'feature') === 0);
        });

        return $features;
    }

    /**
     * @param MergeRequest $feature
     */
    public function removeFeature(MergeRequest $feature)
    {
        $username   = $this->getConfiguration()->getUsername();
        $repository = $this->getConfiguration()->getRepository();

        $this->getApiClient()
             ->gitData()
             ->references()
             ->remove($username, $repository, "heads/{$feature->getName()}");
    }

    /**
     * @param Feature $feature
     *
     * @return Feature
     */
    public function startFeature(Feature $feature)
    {
        $username     = $this->getConfiguration()->getUsername();
        $repository   = $this->getConfiguration()->getRepository();
        $masterBranch = $this->getConfiguration()->getMasterBranch();

        // Check if feature already cerated on remote
        if ($feature) {
            return $feature;
        } else {
            $masterBranchInfo = $this->getApiClient()
                                     ->gitData()
                                     ->references()
                                     ->show($username, $repository, "heads/{$masterBranch}");

            $featureInfo = $this->getApiClient()
                 ->gitData()
                 ->references()
                 ->create($username, $repository, array(
                     'ref' => "refs/heads/{$feature->getName()}",
                     'sha' => $masterBranchInfo['object']['sha'],
                 ));

            return $feature;
        }

    }



    /**
     * @param $pullRequestNumber
     * @param $label
     *
     */
    public function addLabelToPullRequest($pullRequestNumber, $label)
    {
        $client     = $this->getApiClient();
        $repository = $this->getConfiguration()->getRepository();
        $username   = $this->getConfiguration()->getUsername();

        $client->issues()
               ->labels()
               ->add($username, $repository, $pullRequestNumber, $label);
    }

    public function getLabelsByPullRequest($pullRequestNumber)
    {
        $repository = $this->getConfiguration()->getRepository();
        $username   = $this->getConfiguration()->getUsername();
        $client     = $this->getApiClient();

        $labels = $client
            ->issues()
            ->labels()
            ->all($username, $repository, $pullRequestNumber);

        return array_map(function ($label) {
            return $label['name'];
        }, $labels);
    }

    /**
     * @param $label
     *
     * @return array
     */
    public function getPullRequestsByLabel($label)
    {
        $client     = $this->getApiClient();
        $repository = $this->getConfiguration()->getRepository();
        $username   = $this->getConfiguration()->getUsername();

        $pullRequests = $client
            ->issues()
            ->all(
                $username,
                $repository,
                array('state' => 'open', 'labels' => $label)
            );

        $pullRequests = array_filter($pullRequests, function ($pullRequest) {
            return isset($pullRequest['pull_request']);
        });

        // Replace issue object with pull request object
        return array_map(function ($pullRequest) use ($client, $username, $repository) {
            return $client->pullRequest()->show($username, $repository, $pullRequest['number']);
        }, $pullRequests);
    }

    /**
     * @param $feature
     *
     * @return MergeRequest
     */
    public function getMergeRequestByFeature(Feature $feature)
    {
        $repository   = $this->getConfiguration()->getRepository();
        $username     = $this->getConfiguration()->getUsername();
        $masterBranch = $this->getConfiguration()->getMasterBranch();
        $client       = $this->getApiClient();

        $mergeRequests = $client
            ->pullRequest()
            ->all(
                $username,
                $repository,
                array(
                    'state' => 'open',
                    'type'  => 'pr',
                    'head'  => "{$username}:{$feature->getName()}",
                    'base'  => $masterBranch,
                )
            );
        if (count($mergeRequests) === 1) {
            $mergeRequestInfo = $mergeRequests[0];
            $mergeRequest = new MergeRequest($mergeRequestInfo['number']);
            $mergeRequest->setName($mergeRequest['name'])
                         ->setUrl($mergeRequest['html_url'])
                         ->setUrl($mergeRequest['description']);

            return $mergeRequest;
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
        $client       = $this->getApiClient();
        $repository   = $this->getConfiguration()->getRepository();
        $username     = $this->getConfiguration()->getUsername();
        $masterBranch = $this->getConfiguration()->getMasterBranch();

        $pullRequest = $client
            ->pullRequest()
            ->create($username, $repository, array(
                'base'  => $masterBranch,
                'head'  => "{$username}:{$feature}",
                'title' => ucfirst(str_replace('_', ' ', $feature)),
                'body'  => 'PR Description',
            ));

        $pullRequestCommits = $client->pullRequest()->commits(
            $username,
            $repository,
            $pullRequest['number']
        );

        $pullRequestDescription = array_reduce($pullRequestCommits, function ($message, $commit) {
            return $message . '* ' . $commit['commit']['message'] . PHP_EOL;
        }, '');

        $client->pullRequest()->update(
            $username,
            $repository,
            $pullRequest['number'],
            array('body' => $pullRequestDescription)
        );

        return $pullRequest['number'];
    }

    public function compareFeatureWithMaster(Feature $feature)
    {
        $client       = $this->getApiClient();
        $repository   = $this->getConfiguration()->getRepository();
        $username     = $this->getConfiguration()->getUsername();
        $masterBranch = $this->getConfiguration()->getMasterBranch();

        $compareFeatureInfo = $client
            ->repository()
            ->commits()
            ->compare($username, $repository, $masterBranch, $feature);

        return array(
            'status'    => $compareFeatureInfo['status'],
            'ahead_by'  => $compareFeatureInfo['ahead_by'],
            'behind_by' => $compareFeatureInfo['behind_by'],
            'commits'   => count($compareFeatureInfo['commits']),
            'files'     => count($compareFeatureInfo['files']),
        );
    }

    /**
     * @return Version
     */
    public function getReleaseCandidateVersion()
    {
        $version                 = Version::fromString($this->getHighestVersion());
        $releaseCandidateVersion = $version->increase('rc');

        return $releaseCandidateVersion;
    }

    /**
     * @return Version
     */
    public function getReleaseVersion()
    {
        $version        = Version::fromString($this->getHighestVersion());
        $releaseVersion = $version->increase('stable');

        return $releaseVersion;
    }

    /**
     * @return mixed
     */
    protected function getHighestVersion()
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
        $version  = end($versions);

        if (empty($version)) {
            $version = Configuration::DEFAULT_VERSION;
        }

        return $version;
    }

    public function mergeRemoteBranches($targetBranch, $sourceBranch)
    {
        $client     = $this->getApiClient();
        $repository = $this->getConfiguration()->getRepository();
        $username   = $this->getConfiguration()->getUsername();

        $client->repository()->merge($username, $repository, $targetBranch, $sourceBranch);
    }

    public function mergeMergeRequest($pullRequestNumber, $type = 'squash')
    {
        $client     = $this->getApiClient();
        $repository = $this->getConfiguration()->getRepository();
        $username   = $this->getConfiguration()->getUsername();

        $pullRequest      = $client->pullRequest()->show($username, $repository, $pullRequestNumber);
        $pullRequestTitle = "Merge Pull Request {$pullRequest['title']} #{$pullRequestNumber}";

        $client->pullRequest()->merge($username, $repository, $pullRequestNumber,
            $pullRequest['body'], $pullRequest['head']['sha'], $type, $pullRequestTitle);
    }

    public function createReleaseTag($release)
    {
        $client     = $this->getApiClient();
        $repository = $this->getConfiguration()->getRepository();
        $username   = $this->getConfiguration()->getUsername();

        $client->repository()
               ->releases()
               ->create(
                   $username,
                   $repository,
                   array(
                       'tag_name' => $release,
                       'name'     => $release,
                   )
               );
    }

    public function getRCBranchesListByRelease($releaseVersion)
    {
        $client     = $this->getApiClient();
        $repository = $this->getConfiguration()->getRepository();
        $username   = $this->getConfiguration()->getUsername();

        $branches = $client->repository()->branches($username, $repository);

        return array_filter($branches, function ($branch) use ($releaseVersion) {
            return (strpos($branch, $releaseVersion . '-RC') === 0);
        });
    }

    public function getLatestReleaseTag()
    {
        $client     = $this->getApiClient();
        $repository = $this->getConfiguration()->getRepository();
        $username   = $this->getConfiguration()->getUsername();

        $latestRelease = $client->repository()->releases()->latest($username, $repository);

        return $latestRelease['tag_name'];
    }

    public function getLatestTestReleaseTag()
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

    public function createTestReleaseTag($release)
    {
        $client     = $this->getApiClient();
        $repository = $this->getConfiguration()->getRepository();
        $username   = $this->getConfiguration()->getUsername();

        $branchInfo = $client->repository()->branches($username, $repository, $release);
        $release    .= '+' . date('Y-m-d_h-i-s');

        $client->repository()
               ->releases()
               ->create($username, $repository,
                   array(
                       'tag_name'         => $release,
                       'name'             => $release,
                       'prerelease'       => true,
                       'target_commitish' => $branchInfo['commit']['sha'],
                   )
               );
    }


    public function markMergeRequestReadyForTest(MergeRequest $mergeRequest)
    {
        // TODO: Implement markMergeRequestReadyForTest() method.
    }

    public function markMergeRequestReadyForRelease(MergeRequest $mergeRequest)
    {
        // TODO: Implement markMergeRequestReadyForRelease() method.
    }

    /**
     * @return Client
     */
    protected function getApiClient()
    {
        if (empty($this->apiClient)) {
            $client = new Client();
            $client->authenticate(
                $this->getConfiguration()->getToken(),
                null,
                Client::AUTH_HTTP_TOKEN
            );
            $this->apiClient = $client;
        }

        return $this->apiClient;
    }

    public function removeLabelsFromMergeRequest($mergeRequestNumber)
    {
        $repository = $this->getConfiguration()->getRepository();
        $username   = $this->getConfiguration()->getUsername();
        $client     = $this->getApiClient();

        $labels = array(
            $this->getConfiguration()->getLabelForRelease(),
            $this->getConfiguration()->getLabelForTest(),
        );

        foreach ($labels as $label) {
            $client->issues()
                   ->labels()
                   ->remove($username, $repository, $mergeRequestNumber, $label);
        }
    }

    /**
     * @param Feature $feature
     *
     * @return MergeRequest
     */
    public function closeMergeRequestByFeature(Feature $feature)
    {
        // TODO: Implement closeMergeRequestByFeature() method.
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
     * @return Feature
     */
    public function loadFeature(Feature $feature)
    {
        // TODO: Implement loadFeature() method.
    }

    /**
     * @param $feature
     *
     * @return Feature
     */
    public function markFeatureAsNew($feature)
    {
        $mergeRequest = $this->getMergeRequestByFeature($feature);
        if ($mergeRequest) {
            $this->removeLabelsFromMergeRequest($mergeRequest->getNumber());
        }
    }
}