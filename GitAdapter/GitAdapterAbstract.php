<?php

namespace Mirocode\GitReleaseMan\GitAdapter;

use Mirocode\GitReleaseMan\Configuration;
use Mirocode\GitReleaseMan\Entity\Feature;
use Mirocode\GitReleaseMan\Entity\MergeRequest;
use Mirocode\GitReleaseMan\Entity\Release;
use Mirocode\GitReleaseMan\GitAdapter\GitAdapterInterface;
use Mirocode\GitReleaseMan\Version;

abstract class GitAdapterAbstract implements GitAdapterInterface
{
    protected $configuration;
    protected $featureInfo;

    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
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
     * @param mixed $featureInfo
     *
     * @return GitAdapterAbstract
     */
    public function setFeatureInfo($featureInfo)
    {
        $this->featureInfo = $featureInfo;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getFeatureInfo()
    {
        return $this->featureInfo;
    }

    /**
     * @return Configuration
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    abstract public function removeReleaseCandidates(Release $release);
    abstract public function addLabelToFeature(Feature $feature, $label);
    abstract public function removeLabelsFromFeature(Feature $feature);
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
    abstract public function pushFeatureIntoRelease(Release $release, Feature $feature);



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
    public function markFeatureReadyForTest(Feature $feature)
    {
        $label = $this->getConfiguration()->getLabelForTest();
        if (!in_array($label, $feature->getLabels())) {
            $this->addLabelToFeature($feature, $label);
        }
        $feature->setStatus(Feature::STATUS_TEST);

        return $feature;
    }

    /**
     * @param Feature $feature
     *
     * @return Feature
     */
    public function markFeatureReadyForRelease(Feature $feature)
    {
        $label = $this->getConfiguration()->getLabelForRelease();
        if (!in_array($label, $feature->getLabels())) {
            $this->addLabelToFeature($feature, $label);
        }
        $feature->setStatus(Feature::STATUS_RELEASE);

        return $feature;
    }

    /**
     * @param $feature
     *
     * @return Feature
     */
    public function markFeatureAsNew(Feature $feature)
    {
        if ($feature->getMergeRequestNumber()) {
            $this->removeLabelsFromFeature($feature);
        }
        $feature->setStatus(Feature::STATUS_STARTED);

        return $feature;
    }

    /**
     * @return Version
     */
    public function getReleaseCandidateVersion()
    {
        $version = $this->getLatestVersion();

        if ($version->isStable()) {
            $version = $version->increase('minor');
        }

        return $version->increase('rc');
    }

    /**
     * @return Version
     */
    public function getReleaseStableVersion()
    {
        return $this->getLatestVersion()->increase('stable');
    }
}