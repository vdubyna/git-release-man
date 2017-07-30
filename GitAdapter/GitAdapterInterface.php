<?php

namespace Mirocode\GitReleaseMan\GitAdapter;

use Mirocode\GitReleaseMan\Entity\Feature;
use Mirocode\GitReleaseMan\Entity\MergeRequest;

interface GitAdapterInterface
{
    public function removeRemoteBranch($branchName);

    public function createRemoteBranch($branchName);

    /**
     * @param Feature $feature
     *
     * @return MergeRequest
     */
    public function getMergeRequestByFeature(Feature $feature);

    /**
     * @param Feature $feature
     *
     * @return MergeRequest
     */
    public function openMergeRequest(Feature $feature);

    /**
     * @return Feature[]
     */
    public function getFeaturesList();

    /**
     * @param Feature $feature
     *
     * @return mixed
     */
    public function compareFeatureWithMaster(Feature $feature);

    /**
     * @return string
     */
    public function getReleaseVersion();

    /**
     * @return string
     */
    public function getReleaseCandidateVersion();


    public function mergeRemoteBranches($targetBranch, $sourceBranch);

    public function mergeMergeRequest($pullRequestNumber, $type = '');

    public function getRCBranchesListByRelease($releaseVersion);

    public function getLatestReleaseTag();

    public function getLatestTestReleaseTag();

    /**
     * @param Feature $feature
     *
     * @return MergeRequest
     */
    public function closeMergeRequestByFeature(Feature $feature);

    /**
     * @param Feature $feature
     *
     * @return boolean
     */
    public function markMergeRequestReadyForTest(Feature $feature);

    /**
     * @param Feature $feature
     *
     * @return boolean
     */
    public function markMergeRequestReadyForRelease(Feature $feature);
}