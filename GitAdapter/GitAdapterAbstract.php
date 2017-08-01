<?php

namespace Mirocode\GitReleaseMan\GitAdapter;

use Mirocode\GitReleaseMan\Configuration;
use Mirocode\GitReleaseMan\Entity\Feature;
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
}