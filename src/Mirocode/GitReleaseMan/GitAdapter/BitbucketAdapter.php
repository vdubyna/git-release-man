<?php

namespace Mirocode\GitReleaseMan\GitAdapter;

use Composer\Semver\Semver;
use Github\Client;
use InvalidArgumentException;
use Mirocode\GitReleaseMan\GitAdapter\GitAdapterAbstract;
use Mirocode\GitReleaseMan\GitAdapter\GitAdapterInterface;
use Mirocode\GitReleaseMan\Configuration;
use Mirocode\GitReleaseMan\Version;

class BitbucketAdapter extends GitAdapterAbstract implements GitAdapterInterface
{
    /**
     * @var Client
     */
    protected $apiClient;

    /**
     * @return Client
     */
    public function getApiClient()
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

    public function getFeaturesList()
    {
        $username   = $this->getConfiguration()->getUsername();
        $repository = $this->getConfiguration()->getRepositoryName();

        $branches = $this->getApiClient()
                         ->repository()
                         ->branches($username, $repository);

        $branches = array_map(function ($branch) {
            return $branch['name'];
        }, $branches);

        $branches = array_filter($branches, function ($branch) {
            return (strpos($branch, 'feature') === 0);
        });

        return $branches;
    }

    /**
     * @param $branchName
     */
    public function removeRemoteBranch($branchName)
    {
        $username   = $this->getConfiguration()->getUsername();
        $repository = $this->getConfiguration()->getRepositoryName();

        $this->getApiClient()
             ->gitData()
             ->references()
             ->remove($username, $repository, "heads/{$branchName}");
    }

    /**
     * @param $branchName
     */
    public function createRemoteBranch($branchName)
    {
        $username     = $this->getConfiguration()->getUsername();
        $repository   = $this->getConfiguration()->getRepositoryName();
        $masterBranch = $this->getConfiguration()->getMasterBranch();

        $masterBranchInfo = $this->getApiClient()
                                 ->gitData()
                                 ->references()
                                 ->show($username, $repository, "heads/{$masterBranch}");

        $this->getApiClient()
             ->gitData()
             ->references()
             ->create($username, $repository, array(
                 'ref' => "refs/heads/{$branchName}",
                 'sha' => $masterBranchInfo['object']['sha'],
             ));
    }

    public function removeLabelsFromPullRequest($pullRequestNumber)
    {
        $repository = $this->getConfiguration()->getRepositoryName();
        $username   = $this->getConfiguration()->getUsername();
        $client     = $this->getApiClient();

        $labels = array(
            $this->getConfiguration()->getPRLabelForRelease(),
            $this->getConfiguration()->getPRLabelForTest(),
        );

        foreach ($labels as $label) {
            $client->issues()
                   ->labels()
                   ->remove($username, $repository, $pullRequestNumber, $label);
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
        $repository = $this->getConfiguration()->getRepositoryName();
        $username   = $this->getConfiguration()->getUsername();

        $client->issues()
               ->labels()
               ->add($username, $repository, $pullRequestNumber, $label);
    }

    public function getLabelsByPullRequest($pullRequestNumber)
    {
        $repository = $this->getConfiguration()->getRepositoryName();
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
        $repository = $this->getConfiguration()->getRepositoryName();
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
     * @param $featureName
     *
     * @return mixed
     */
    public function getPullRequestByFeature($featureName)
    {
        $repository   = $this->getConfiguration()->getRepositoryName();
        $username     = $this->getConfiguration()->getUsername();
        $masterBranch = $this->getConfiguration()->getMasterBranch();
        $client       = $this->getApiClient();

        $pullRequests = $client
            ->pullRequest()
            ->all(
                $username,
                $repository,
                array(
                    'state' => 'open',
                    'type'  => 'pr',
                    'head'  => "{$username}:{$featureName}",
                    'base'  => $masterBranch,
                )
            );

        return (count($pullRequests) === 1) ? $pullRequests[0] : array();
    }

    /**
     * @param $featureName
     *
     * @return string
     */
    public function openPullRequest($featureName)
    {
        $client       = $this->getApiClient();
        $repository   = $this->getConfiguration()->getRepositoryName();
        $username     = $this->getConfiguration()->getUsername();
        $masterBranch = $this->getConfiguration()->getMasterBranch();

        $pullRequest = $client
            ->pullRequest()
            ->create($username, $repository, array(
                'base'  => $masterBranch,
                'head'  => "{$username}:{$featureName}",
                'title' => ucfirst(str_replace('_', ' ', $featureName)),
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

    public function compareFeatureWithMaster($featureName)
    {
        $client       = $this->getApiClient();
        $repository   = $this->getConfiguration()->getRepositoryName();
        $username     = $this->getConfiguration()->getUsername();
        $masterBranch = $this->getConfiguration()->getMasterBranch();

        $compareFeatureInfo = $client
            ->repository()
            ->commits()
            ->compare($username, $repository, $masterBranch, $featureName);

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

        return $releaseCandidateVersion->__toString();
    }

    /**
     * @return Version
     */
    public function getReleaseVersion()
    {
        $version        = Version::fromString($this->getHighestVersion());
        $releaseVersion = $version->increase('stable');

        return $releaseVersion->__toString();
    }

    /**
     * @return mixed
     */
    protected function getHighestVersion()
    {
        $username   = $this->getConfiguration()->getUsername();
        $repository = $this->getConfiguration()->getRepositoryName();
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
        $repository = $this->getConfiguration()->getRepositoryName();
        $username   = $this->getConfiguration()->getUsername();

        $client->repository()->merge($username, $repository, $targetBranch, $sourceBranch);
    }

    public function mergePullRequest($pullRequestNumber, $type = 'squash')
    {
        $client     = $this->getApiClient();
        $repository = $this->getConfiguration()->getRepositoryName();
        $username   = $this->getConfiguration()->getUsername();

        $pullRequest      = $client->pullRequest()->show($username, $repository, $pullRequestNumber);
        $pullRequestTitle = "Merge Pull Request {$pullRequest['title']} #{$pullRequestNumber}";

        $client->pullRequest()->merge($username, $repository, $pullRequestNumber,
            $pullRequest['body'], $pullRequest['head']['sha'], $type, $pullRequestTitle);
    }

    public function createReleaseTag($release)
    {
        $client     = $this->getApiClient();
        $repository = $this->getConfiguration()->getRepositoryName();
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
        $repository = $this->getConfiguration()->getRepositoryName();
        $username   = $this->getConfiguration()->getUsername();

        $branches = $client->repository()->branches($username, $repository);

        return array_filter($branches, function ($branch) use ($releaseVersion) {
            return (strpos($branch, $releaseVersion . '-RC') === 0);
        });
    }

    public function getLatestReleaseTag()
    {
        $client     = $this->getApiClient();
        $repository = $this->getConfiguration()->getRepositoryName();
        $username   = $this->getConfiguration()->getUsername();

        $latestRelease = $client->repository()->releases()->latest($username, $repository);

        return $latestRelease['tag_name'];
    }

    public function getLatestTestReleaseTag()
    {
        $client     = $this->getApiClient();
        $repository = $this->getConfiguration()->getRepositoryName();
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
        $repository = $this->getConfiguration()->getRepositoryName();
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
}