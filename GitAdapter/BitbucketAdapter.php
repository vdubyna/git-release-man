<?php

namespace Mirocode\GitReleaseMan\GitAdapter;

use Bitbucket\API\Http\Listener\BasicAuthListener;
use Bitbucket\API\Repositories\Refs\Branches;
use Composer\Semver\Semver;
use InvalidArgumentException;
use Mirocode\GitReleaseMan\Entity\Feature;
use Mirocode\GitReleaseMan\Entity\Feature;
use Mirocode\GitReleaseMan\GitAdapter\GitAdapterAbstract;
use Mirocode\GitReleaseMan\GitAdapter\GitAdapterInterface;
use Mirocode\GitReleaseMan\Configuration;
use Mirocode\GitReleaseMan\Version;

class BitbucketAdapter extends GitAdapterAbstract implements GitAdapterInterface
{

    public function getFeaturesList()
    {
        $username   = $this->getConfiguration()->getUsername();
        $token      = $this->getConfiguration()->getToken();
        $repository = $this->getConfiguration()->getRepository();

        $branches = new Branches();
        $branches->getClient()->addListener(
            new BasicAuthListener($username, $token)
        );

        $branches = $branches->all($username, $repository);
        $branches = json_decode($branches->getContent(), true);
        $branches = $branches['values'];

        if (empty($branches)) {
            return array();
        }

        $branches = array_map(function ($branch) {
            return $branch['name'];
        }, $branches);

        $branches = array_filter($branches, function ($branch) {
            return (strpos($branch, 'feature') === 0);
        });


        return $branches;
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
     * @param Feature $feature
     *
     * @return Feature|null
     */
    public function getMergeRequestByFeature(Feature $feature)
    {
        // TODO: Implement getMergeRequestByFeature() method.
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
        // TODO: Implement startFeature() method.
    }
}