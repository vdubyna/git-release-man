<?php

namespace Mirocode\GitReleaseMan\GitAdapter;

use Mirocode\GitReleaseMan\Configuration;
use Mirocode\GitReleaseMan\Entity\Feature;
use Mirocode\GitReleaseMan\Entity\Release;
use Mirocode\GitReleaseMan\GitAdapter\GitAdapterInterface as GitAdapterInterface;
use Mirocode\GitReleaseMan\Version;
use Symfony\Component\Console\Style\StyleInterface;

abstract class GitAdapterAbstract implements GitAdapterInterface
{
    protected $configuration;
    protected $styleHelper;

    const ADAPTER_NAME = 'gitlocal';

    public function __construct(Configuration $configuration, StyleInterface $styleHelper)
    {
        $this->configuration = $configuration;
        $this->styleHelper   = $styleHelper;
    }

    /**
     * Force Adapters to load feature info from repository
     *
     * @param $featureName
     *
     * @return Feature
     */
    abstract public function buildFeature($featureName);

    /**
     * @param Release $release
     *
     * @return void
     */
    abstract public function removeReleaseCandidates(Release $release);

    /**
     * @param Feature $feature
     * @param         $label
     *
     * @return void
     */
    abstract public function addLabelToFeature(Feature $feature, $label);

    /**
     * @param Feature $feature
     *
     * @return void
     */
    abstract public function removeLabelsFromFeature(Feature $feature);

    /**
     * @param Feature $feature
     *
     * @return array
     */
    abstract public function getFeatureLabels(Feature $feature);

    /**
     * @return Version
     */
    abstract protected function getLatestVersion();

    /**
     * @param Release $release
     *
     * @return Release
     */
    abstract public function startReleaseCandidate(Release $release);

    /**
     * @param Release $release
     */
    public function cleanupRelease($release)
    {
        foreach ($release->getFeatures() as $feature) {
            $this->closeFeature($feature);
        }

        $this->removeReleaseCandidates($release);
    }

    /**
     * @return Feature[]
     */
    public function getFeaturesByLabel($label)
    {
        return array_filter($this->getFeaturesList(), function (Feature $feature) use ($label) {
            return (in_array($label, $feature->getLabels())) ? true : false;
        });
    }

    /**
     * @param Feature $feature
     *
     * @return Feature
     */
    public function markFeatureReadyForReleaseCandidate(Feature $feature)
    {
        $label = $this->getConfiguration()->getLabelForReleaseCandidate();
        if (!in_array($label, $feature->getLabels())) {
            $this->addLabelToFeature($feature, $label);
        }
        $feature->setStatus(Feature::STATUS_RELEASE_CANDIDATE);

        return $feature;
    }

    /**
     * @param Feature $feature
     *
     * @return Feature
     */
    public function markFeatureReadyForReleaseStable(Feature $feature)
    {
        $label = $this->getConfiguration()->getLabelForReleaseStable();
        if (!in_array($label, $feature->getLabels())) {
            $this->addLabelToFeature($feature, $label);
        }
        $feature->setStatus(Feature::STATUS_RELEASE_STABLE);

        return $feature;
    }

    /**
     * @param $feature
     *
     * @return Feature
     */
    public function markFeatureAsNew(Feature $feature)
    {
        $this->removeLabelsFromFeature($feature);
        $feature->setStatus(Feature::STATUS_STARTED);

        return $feature;
    }

    /**
     * @param string $type
     *
     * @return Version
     */
    public function getReleaseCandidateVersion($type = Version::TYPE_MINOR)
    {
        $version = $this->getLatestVersion();

        if ($version->isStable()) {
            $version = $version->increase($type);
        }

        return $version->increase(Version::STABILITY_RC);
    }

    /**
     * @return Version
     */
    public function getReleaseStableVersion()
    {
        return $this->getLatestVersion()->increase(Version::STABILITY_STABLE);
    }

    /**
     * Remove release candidate
     *
     * @param Release $release
     *
     * @return void
     */
    abstract public function removeReleaseCandidate(Release $release): void;

    /**
     * @return StyleInterface
     */
    protected function getStyleHelper()
    {
        return $this->styleHelper;
    }

    /**
     * @return Configuration
     */
    protected function getConfiguration()
    {
        return $this->configuration;
    }
}
