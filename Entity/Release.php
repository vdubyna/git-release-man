<?php

namespace Mirocode\GitReleaseMan\Entity;

class Release
{
    const STATUS_CLOSED          = 'closed';
    const STATUS_STARTED         = 'started';
    const STATUS_NEW             = 'new';

    const TYPE_RELEASE_STABLE    = 'stable';
    const TYPE_RELEASE_CANDIDATE = 'candidate';

    /**
     * @var string
     */
    protected $version;
    protected $branch;

    /**
     * @var Feature[]
     */
    protected $features = array();
    protected $metadata;
    protected $status;
    protected $isStable;

    public function __construct($version, $branch, $isStable)
    {
        $this->version = $version;
        $this->branch = $branch;
        //TODO Load the status from repository.
        $this->setStatus(self::STATUS_NEW);
        $this->isStable = $isStable;
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @param Feature $feature
     */
    public function addFeature(Feature $feature)
    {
        $this->features[] = $feature;
    }

    /**
     * @return Feature[]
     */
    public function getFeatures()
    {
        return $this->features;
    }

    /**
     * @return mixed
     */
    public function getBranch()
    {
        return $this->branch;
    }

    /**
     * @param string $metadata
     *
     * @return Release
     */
    public function setMetadata($metadata)
    {
        $this->metadata = $metadata;

        return $this;
}

    /**
     * @return mixed
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * @param mixed $status
     *
     * @return Release
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
}

    /**
     * @return mixed
     */
    public function getStatus()
    {
        return $this->status;
    }

    public function isStable()
    {
        return $this->isStable;
    }
}