<?php

namespace Mirocode\GitReleaseMan\GitAdapter;

use Mirocode\GitReleaseMan\Configuration;
use Mirocode\GitReleaseMan\Entity\Feature;
use Mirocode\GitReleaseMan\Entity\FeatureInterface;
use Mirocode\GitReleaseMan\GitAdapter\GitAdapterInterface;

abstract class GitAdapterAbstract implements GitAdapterInterface
{
    protected $configuration;

    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * @return Configuration
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * Factory method to create feature
     *
     * @return FeatureInterface
     */
    public function createFeature($featureName)
    {
        $feature = new Feature($featureName);

        return $feature;
    }
}