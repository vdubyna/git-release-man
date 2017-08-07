<?php

namespace Mirocode\GitReleaseMan\GitAdapter;

use Mirocode\GitReleaseMan\Configuration;
use Mirocode\GitReleaseMan\Entity\Feature;
use Mirocode\GitReleaseMan\Entity\MergeRequest;
use Mirocode\GitReleaseMan\Entity\Release;
use Mirocode\GitReleaseMan\GitAdapter\GitAdapterInterface;

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

    abstract public function removeReleaseCandidates($release);

    abstract public function getMergeRequestsByLabel($label);
    abstract public function addLabelToFeature(Feature $feature, $label);
    abstract public function removeLabelsFromFeature(Feature $feature);

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
        $mergeRequests = $this->getMergeRequestsByLabel($label);

        return array_map(function (MergeRequest $mergeRequest) {
            $feature = $this->buildFeature($mergeRequest->getSourceBranch());
            $feature->setMergeRequest($mergeRequest)
                    ->setMergeRequestNumber($mergeRequest->getNumber());

            return $feature;
        }, $mergeRequests);
    }

    /**
     * @param Feature $feature
     *
     * @return Feature
     */
    public function markFeatureReadyForTest(Feature $feature)
    {
        $this->addLabelToFeature(
            $feature,
            $this->getConfiguration()->getLabelForTest()
        );
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
        $this->addLabelToFeature(
            $feature,
            $this->getConfiguration()->getLabelForRelease()
        );
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
}