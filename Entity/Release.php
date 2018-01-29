<?php

namespace Mirocode\GitReleaseMan\Entity;

use Mirocode\GitReleaseMan\ExitException;
use Mirocode\GitReleaseMan\Version;

class Release
{
    const STATUS_NEW             = 'new';
    const STATUS_STARTED         = 'started';
    const STATUS_CLOSED          = 'closed';
    const TYPE_RELEASE_STABLE    = 'stable';
    const TYPE_RELEASE_CANDIDATE = 'candidate';

    protected $version;
    protected $branch;
    protected $features = [];
    protected $metadata;
    protected $status;
    protected $isStable;

    public function __construct(Version $version, $branch, $type)
    {
        $this->version = $version->__toString();
        $this->branch  = $branch;
        $this->setStatus(self::STATUS_NEW);
        $this->setType($type);
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return ($this->getMetadata()) ? $this->version . '+' . $this->getMetadata() : $this->version;
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
     * @return mixed
     */
    public function getStatus()
    {
        return $this->status;
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
     * @param Release::TYPE_RELEASE_STABLE|Release::TYPE_RELEASE_CANDIDATE $type
     *
     * @throws ExitException
     * @return $this
     */
    public function setType($type)
    {
        if (self::TYPE_RELEASE_STABLE === $type) {
            $this->isStable = true;
        } elseif (self::TYPE_RELEASE_CANDIDATE === $type) {
            $this->isStable = false;
        } else {
            throw new ExitException("Type {$type} is not valid");
        }

        return $this;
    }

    public function isStable()
    {
        return $this->isStable;
    }
}