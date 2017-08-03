<?php
/**
 * Created by PhpStorm.
 * User: vdubyna
 * Date: 8/1/17
 * Time: 06:50
 */

namespace Mirocode\GitReleaseMan\Tests;


use Mirocode\GitReleaseMan\Entity\Feature;
use Mirocode\GitReleaseMan\Entity\Feature;
use Mirocode\GitReleaseMan\GitAdapter\GitAdapterAbstract;
use Mirocode\GitReleaseMan\GitAdapter\GitAdapterInterface;

class GitAdapter extends GitAdapterAbstract implements GitAdapterInterface
{

    /**
     * @param Feature $feature
     *
     * @return Feature
     */
    public function closeFeature(Feature $feature)
    {
        $feature->setStatus(Feature::STATUS_CLOSE);
        return $feature;
    }

    /**
     * @param Feature $feature
     *
     * @return Feature
     */
    public function loadFeature(Feature $feature)
    {
        return $feature;
    }

    /**
     * @param Feature $feature
     *
     * @return Feature|null
     */
    public function getMergeRequestByFeature(Feature $feature)
    {
        return new Feature('12');
    }

    /**
     * @param Feature $feature
     *
     * @return Feature|null
     */
    public function openMergeRequestByFeature(Feature $feature)
    {
        // TODO: Implement openMergeRequestByFeature() method.
    }

    /**
     * @return Feature[]
     */
    public function getFeaturesList()
    {
        return array(new Feature('feature-my-cool-library'));
    }

    /**
     * @param Feature $feature
     *
     * @return mixed
     */
    public function compareFeatureWithMaster(Feature $feature)
    {
        // TODO: Implement compareFeatureWithMaster() method.
    }

    /**
     * @return string
     */
    public function getReleaseVersion()
    {
        // TODO: Implement getReleaseVersion() method.
    }

    /**
     * @return string
     */
    public function getReleaseCandidateVersion()
    {
        // TODO: Implement getReleaseCandidateVersion() method.
    }

    public function mergeRemoteBranches($targetBranch, $sourceBranch)
    {
        // TODO: Implement mergeRemoteBranches() method.
    }

    public function mergeMergeRequest($pullRequestNumber, $type = '')
    {
        // TODO: Implement mergeMergeRequest() method.
    }

    public function getRCBranchesListByRelease($releaseVersion)
    {
        // TODO: Implement getRCBranchesListByRelease() method.
    }

    public function getLatestReleaseTag()
    {
        // TODO: Implement getLatestReleaseTag() method.
    }

    public function getLatestTestReleaseTag()
    {
        // TODO: Implement getLatestTestReleaseTag() method.
    }

    /**
     * @param Feature $feature
     *
     * @return Feature
     */
    public function closeMergeRequestByFeature(Feature $feature)
    {
        // TODO: Implement closeMergeRequestByFeature() method.
    }

    /**
     * @param Feature $mergeRequest
     *
     * @return Feature
     */
    public function markMergeRequestReadyForTest(Feature $mergeRequest)
    {
        // TODO: Implement markMergeRequestReadyForTest() method.
    }

    /**
     * @param Feature $mergeRequest
     *
     * @return Feature
     */
    public function markMergeRequestReadyForRelease(Feature $mergeRequest)
    {
        // TODO: Implement markMergeRequestReadyForRelease() method.
    }

    /**
     * @param Feature $feature
     *
     * @return Feature
     */
    public function startFeature(Feature $feature)
    {
        $feature->setStatus(Feature::STATUS_NEW);
        return $feature;
    }

    /**
     * @param Feature $feature
     *
     * @return Feature
     */
    public function markFeatureAsNew(Feature $feature)
    {
        $feature->setStatus(Feature::STATUS_NEW);
        return $feature;
    }
}