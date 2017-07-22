<?php
/**
 * Created by PhpStorm.
 * User: vdubyna
 * Date: 7/21/17
 * Time: 15:13
 */

namespace Mirocode\GitReleaseMan;


interface GitAdapter
{
    /**
     * @param $branchName
     *
     * @return void
     */
    public function removeRemoteBranch($branchName);

    public function createRemoteBranch($branchName);

    public function removeLabelsFromPullRequest($pullRequestNumber);

    public function addLabelToPullRequest($pullRequestNumber, $label);

    public function getPullRequestByFeature($featureName);

    public function getPullRequestsByLabel($label);

    public function openPullRequest($featureName);

    public function getFeaturesList();

    public function getLabelsByPullRequest($pullRequest);

    public function compareFeatureWithMaster($featureName);

    public function getReleaseVersion();

    public function getReleaseCandidateVersion();

    public function mergeRemoteBranches($targetBranch, $sourceBranch);

    public function mergePullRequest($pullRequestNumber, $type = '');

    public function createReleaseTag($release);

    public function createTestReleaseTag($release);

    public function getRCBranchesListByRelease($releaseVersion);

    public function getLatestReleaseTag();

    public function getLatestTestReleaseTag();
}