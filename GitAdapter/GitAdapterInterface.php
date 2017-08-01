<?php

namespace Mirocode\GitReleaseMan\GitAdapter;

use Mirocode\GitReleaseMan\Entity\Feature;
use Mirocode\GitReleaseMan\Entity\MergeRequest;

interface GitAdapterInterface
{

    /**
     * @param Feature $feature
     *
     * @return Feature
     */
    public function closeFeature(Feature $feature);

    /**
     * @param Feature $feature
     *
     * @return Feature
     */
    public function loadFeature(Feature $feature);

    /**
     * @param Feature $feature
     *
     * @return MergeRequest|null
     */
    public function getMergeRequestByFeature(Feature $feature);

    /**
     * @param Feature $feature
     *
     * @return MergeRequest|null
     */
    public function openMergeRequestByFeature(Feature $feature);

    /**
     * @return MergeRequest[]
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
     * @param MergeRequest $mergeRequest
     *
     * @return MergeRequest
     */
    public function markMergeRequestReadyForTest(MergeRequest $mergeRequest);

    /**
     * @param MergeRequest $mergeRequest
     *
     * @return MergeRequest
     */
    public function markMergeRequestReadyForRelease(MergeRequest $mergeRequest);

    /**
     * @param Feature $feature
     *
     * @return Feature
     */
    public function startFeature(Feature $feature);
}