<?php

namespace Mirocode\GitReleaseMan\Tests\GitAdapter;

use PHPUnit\Framework\TestCase;

class GithubAdapterTest extends TestCase
{

    public function getFeaturesList()
    {

    }

    /**
     * @param $branchName
     */
    public function removeRemoteBranch($branchName)
    {

    }

    /**
     * @param $branchName
     */
    public function createRemoteBranch($branchName)
    {

    }

    public function removeLabelsFromPullRequest($pullRequestNumber)
    {

    }

    /**
     * @param $pullRequestNumber
     * @param $label
     *
     */
    public function addLabelToPullRequest($pullRequestNumber, $label)
    {

    }

    public function getLabelsByPullRequest($pullRequestNumber)
    {

    }

    /**
     * @param $label
     *
     * @return array
     */
    public function getPullRequestsByLabel($label)
    {

    }

    /**
     * @param $feature
     *
     * @return mixed
     */
    public function getMergeRequestByFeature($feature)
    {

    }

    /**
     * @param $feature
     *
     * @return string
     */
    public function openMergeRequest($feature)
    {

    }

    public function compareFeatureWithMaster($feature)
    {

    }

    /**
     * @return Version
     */
    public function getReleaseCandidateVersion()
    {

    }

    /**
     * @return Version
     */
    public function getReleaseVersion()
    {

    }

    /**
     * @return mixed
     */
    protected function getHighestVersion()
    {

    }

    public function mergeRemoteBranches($targetBranch, $sourceBranch)
    {

    }

    public function mergeMergeRequest($pullRequestNumber, $type = 'squash')
    {

    }

    public function createReleaseTag($release)
    {

    }

    public function getRCBranchesListByRelease($releaseVersion)
    {

    }

    public function getLatestReleaseTag()
    {

    }

    public function getLatestTestReleaseTag()
    {

    }

    public function createTestReleaseTag($release)
    {

    }

    public function closeMergeRequest($featureName)
    {
        // TODO: Implement closeMergeRequest() method.
    }

    public function markMergeRequestReadyForTest(FeatureInterface $feature)
    {
        // TODO: Implement markMergeRequestReadyForTest() method.
    }

    public function markMergeRequestReadyForRelease(FeatureInterface $feature)
    {
        // TODO: Implement markMergeRequestReadyForRelease() method.
    }


}