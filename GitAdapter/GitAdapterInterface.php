<?php

namespace Mirocode\GitReleaseMan\GitAdapter;

use Mirocode\GitReleaseMan\Entity\FeatureInterface;

interface GitAdapterInterface
{
    public function removeRemoteBranch($branchName);

    public function createRemoteBranch($branchName);

    public function getMergeRequestByFeature(FeatureInterface $feature);

    public function openMergeRequest(FeatureInterface $feature);

    public function getFeaturesList();

    public function compareFeatureWithMaster(FeatureInterface $feature);

    public function getReleaseVersion();

    public function getReleaseCandidateVersion();

    public function mergeRemoteBranches($targetBranch, $sourceBranch);

    public function mergeMergeRequest($pullRequestNumber, $type = '');

    public function getRCBranchesListByRelease($releaseVersion);

    public function getLatestReleaseTag();

    public function getLatestTestReleaseTag();

    public function closeMergeRequest($featureName);
}