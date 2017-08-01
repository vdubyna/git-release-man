<?php

namespace Mirocode\GitReleaseMan\Entity;

class Feature
{
    /**
     * @var string
     */
    protected $name;
    protected $status;

    const STATUS_NEW     = 'new';
    const STATUS_STARTED = 'started';
    const STATUS_TEST    = 'test';
    const STATUS_RELEASE = 'release';
    const STATUS_CLOSE   = 'close';

    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param $status
     *
     * @return $this
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
}