<?php

namespace Mirocode\GitReleaseMan\GitAdapter;

use Composer\Semver\Semver;
use Github\Client;
use InvalidArgumentException;
use Mirocode\GitReleaseMan\Entity\Feature;
use Mirocode\GitReleaseMan\Entity\MergeRequest;
use Mirocode\GitReleaseMan\Entity\Release;
use Mirocode\GitReleaseMan\ExitException;
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

        $features = array_filter($features, function (Feature $feature) {
            return (strpos($feature->getName(), 'feature') === 0);
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

        $username     = $this->getConfiguration()->getUsername();
        $repository   = $this->getConfiguration()->getRepository();
        $masterBranch = $this->getConfiguration()->getMasterBranch();

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
        $feature->setStatus(Feature::STATUS_STARTED)
                ->setCommit($featureInfo['object']['sha']);

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
    protected function addLabelToFeature(Feature $feature, $label)
    {
        $client     = $this->getApiClient();
        $repository = $this->getConfiguration()->getRepository();
        $username   = $this->getConfiguration()->getUsername();

        $client->issues()
               ->labels()
               ->add($username, $repository, $feature->getMergeRequestNumber(), $label);
        $feature->addLabel($label);

        return $feature;
    }

    public function getLabelsByMergeRequest($mergeRequestNumber)
    {
        $repository = $this->getConfiguration()->getRepository();
        $username   = $this->getConfiguration()->getUsername();

        $labels = $this->getApiClient()
            ->issues()
            ->labels()
            ->all($username, $repository, $mergeRequestNumber);

        return array_map(function ($label) {
            return $label['name'];
        }, $labels);
    }

    /**
     * @param $label
     *
     * @return MergeRequest[]
     */
    public function getMergeRequestsByLabel($label)
    {
        $client     = $this->getApiClient();
        $repository = $this->getConfiguration()->getRepository();
        $username   = $this->getConfiguration()->getUsername();

        $issues = $client
            ->issues()
            ->all(
                $username,
                $repository,
                array('state' => 'open', 'labels' => $label)
            );

        $issues = array_filter($issues, function ($item) {
            return isset($item['pull_request']);
        });

        // Replace issue object with pull request object
        return array_map(function ($item) use ($client, $username, $repository) {
            $mergeRequestInfo = $client->pullRequest()->show($username, $repository, $item['number']);

            $mergeRequest = new MergeRequest($mergeRequestInfo['number']);
            $mergeRequest->setName($mergeRequestInfo['title'])
                         ->setIsMergeable($mergeRequestInfo['mergeable'])
                         ->setDescription($mergeRequestInfo['body'])
                         ->setUrl($mergeRequestInfo['html_url'])
                         ->setCommit($mergeRequestInfo['head']['sha'])
                        ->setSourceBranch($mergeRequestInfo['head']['ref'])
                        ->setTargetBranch($mergeRequestInfo['base']['ref']);

            return $mergeRequest;
        }, $issues);
    }

    /**
     * @param Feature $feature
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
            $mergeRequest->setName($mergeRequestInfo['title'])
                         ->setUrl($mergeRequestInfo['html_url'])
                         ->setDescription($mergeRequestInfo['body']);

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

        $mergeRequest = $client
            ->pullRequest()
            ->create($username, $repository, array(
                'base'  => $masterBranch,
                'head'  => "{$username}:{$feature->getName()}",
                'title' => ucfirst(str_replace('_', ' ', $feature->getName())),
                'body'  => 'Description',
            ));

        $pullRequestCommits = $client->pullRequest()->commits(
            $username,
            $repository,
            $mergeRequest['number']
        );

        $pullRequestDescription = array_reduce($pullRequestCommits, function ($message, $commit) {
            return $message . '* ' . $commit['commit']['message'] . PHP_EOL;
        }, '');

        $mergeRequestInfo = $client->pullRequest()->update(
            $username,
            $repository,
            $mergeRequest['number'],
            array('body' => $pullRequestDescription)
        );

        $mergeRequest = new MergeRequest($mergeRequestInfo['number']);

        return $mergeRequest;
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
        $version = Version::fromString($this->getHighestVersion());

        if ($version->isStable()) {
            $version = $version->increase('minor');
        }

        $releaseCandidateVersion = $version->increase('rc');

        return $releaseCandidateVersion;
    }

    /**
     * @return Version
     */
    public function getReleaseStableVersion()
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

        $versions = array_merge($versionsTags, $versionsBranches);
        $versions = Semver::sort($versions);
        $version  = end($versions);

        if (empty($version)) {
            $version = Configuration::DEFAULT_VERSION;
        }

        return $version;
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
    public function pushFeatureIntoRelease(Release $release, Feature $feature)
    {
        $client     = $this->getApiClient();
        $repository = $this->getConfiguration()->getRepository();
        $username   = $this->getConfiguration()->getUsername();

        $client->pullRequest()->merge(
            $username,
            $repository,
            $feature->getMergeRequestNumber(),
            "Add feature {$feature->getName()}",
            $feature->getMergeRequest()->getCommit(),
            'squash'
        );
        $release->addFeature($feature);
    }

    /**
     * @param Release $release
     * @param string  $metadata
     *
     * @return Release
     */
    public function createReleaseTag(Release $release, $metadata = '')
    {
        $client            = $this->getApiClient();
        $repository        = $this->getConfiguration()->getRepository();
        $username          = $this->getConfiguration()->getUsername();

        $release->setMetadata($metadata); // TODO move to release object
        $releaseBranchInfo = $client
            ->repository()
            ->branches($username, $repository, $release->getBranch());
        $releaseTag = (empty($metadata)) ? $release->getVersion() : $release->getVersion() . '+' . $metadata;
        $client->repository()
               ->releases()
               ->create($username, $repository,
                   array(
                       'tag_name'         => $releaseTag,
                       'name'             => $releaseTag,
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

        $feature->setStatus(Feature::STATUS_CLOSE);

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
        $username    = $this->getConfiguration()->getUsername();
        $repository  = $this->getConfiguration()->getRepository();

        try {
            $featureInfo = $this->getApiClient()->repository()->branches($username, $repository, $featureName);
        } catch (\Github\Exception\RuntimeException $e) {
            $featureInfo = array();
        }

        $feature = new Feature($featureName);

        if (empty($featureInfo)) {
            $feature->setStatus(Feature::STATUS_NEW);
        } else {
            $feature->setStatus(Feature::STATUS_STARTED)
                    ->setCommit($featureInfo['commit']['sha']);

            $mergeRequest = $this->getMergeRequestByFeature($feature);

            if ($mergeRequest && $mergeRequest->getNumber()) {
                $labels = $this->getLabelsByMergeRequest($mergeRequest->getNumber());
                $feature->setLabels($labels)
                    ->setMergeRequestNumber($mergeRequest->getNumber());

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

    /**
     * @param Feature $feature
     */
    public function removeLabelsFromFeature(Feature $feature)
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
                   ->remove($username, $repository, $feature->getMergeRequestNumber(), $label);
        }
    }

    /**
     * @param Release $release
     */
    public function removeReleaseCandidates($release)
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

}