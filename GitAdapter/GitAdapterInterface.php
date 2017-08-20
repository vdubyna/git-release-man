<?php

namespace Mirocode\GitReleaseMan\GitAdapter;

use Mirocode\GitReleaseMan\Entity\Feature;
use Mirocode\GitReleaseMan\Entity\Release;
use Mirocode\GitReleaseMan\Version;

interface GitAdapterInterface
{
    /**
     * @return Feature[]
     */
    public function getFeaturesList();

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
    public function markFeatureReadyForReleaseCandidate(Feature $feature);

    /**
     * @param Feature $feature
     *
     * @return Feature
     */
    public function markFeatureReadyForReleaseStable(Feature $feature);

    /**
     * @param Feature $feature
     *
     * @return Feature
     */
    public function startFeature(Feature $feature);

    /**
     * @param Feature $feature
     *
     * @return Feature
     */
    public function closeFeature(Feature $feature);

    /**
     * @param Release $release
     *
     * @return Release
     */
    public function createReleaseTag(Release $release);

    /**
     * @param Release $release
     * @param Feature $feature
     *
     * @return void
     */
    public function pushFeatureIntoReleaseCandidate(Release $release, Feature $feature);

    /**
     * @param Release $release
     * @param Feature $feature
     *
     * @return void
     */
    public function pushFeatureIntoReleaseStable(Release $release, Feature $feature);

    /**
     * @param string $label
     *
     * @return Feature[]
     */
    public function getFeaturesByLabel($label);

    /**
     * @param Feature $feature
     * @param Release $release
     *
     * @return bool
     */
    public function isFeatureReadyForRelease(Feature $feature, Release $release);

    /**
     * @return Version
     */
    public function getReleaseStableVersion();

    /**
     * @return Version
     */
    public function getReleaseCandidateVersion();

    /**
     * @return string
     */
    public function getLatestReleaseStableTag();

    /**
     * @return string
     */
    public function getLatestReleaseCandidateTag();
}