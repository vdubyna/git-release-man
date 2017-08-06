<?php

namespace Mirocode\GitReleaseMan\GitAdapter;

use Mirocode\GitReleaseMan\Entity\Feature;
use Mirocode\GitReleaseMan\Entity\MergeRequest;
use Mirocode\GitReleaseMan\Entity\Release;
use Mirocode\GitReleaseMan\Version;

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
     * @return MergeRequest
     */
    public function getMergeRequestByFeature(Feature $feature);

    /**
     * @param Feature $feature
     *
     * @return MergeRequest
     */
    public function openMergeRequestByFeature(Feature $feature);

    /**
     * @return Feature[]
     */
    public function getFeaturesList();

    public function getLatestReleaseTag();

    public function getLatestTestReleaseTag();


    /**
     * @param Feature $feature
     *
     * @return Feature
     */
    public function markFeatureAsNew(Feature $feature);

    /**
     * @param Feature $feature
     *
     * @return Feature
     */
    public function markFeatureReadyForTest(Feature $feature);

    /**
     * @param Feature $feature
     *
     * @return Feature
     */
    public function markFeatureReadyForRelease(Feature $feature);

    /**
     * @param Feature $feature
     *
     * @return Feature
     */
    public function startFeature(Feature $feature);

    /**
     * @return Version
     */
    public function getReleaseCandidateVersion();


    /**
     * @return Version
     */
    public function getReleaseVersion();


    /**
     * @param Feature[] $features
     *
     * @return Release
     */
    public function buildReleaseCandidate($features);

    /**
     * @param Feature[] $features
     *
     * @return Release
     */
    public function buildReleaseStable($features);

    /**
     * @param Release $release
     *
     * @return Release
     */
    public function createReleaseTag(Release $release, $metadata = '');

    /**
     * @param Release $release
     * @param Feature $feature
     *
     * @return void
     */
    public function pushFeatureIntoReleaseCandidate(Release $release, Feature $feature);

    /**
     * @param $label
     *
     * @return Feature[]
     */
    public function getFeaturesByLabel($label);

}