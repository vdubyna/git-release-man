<?php

namespace Mirocode\GitReleaseMan\Entity;

class Release
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var Feature[]
     */
    protected $mergeRequests = array();

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
     * @param Feature $mergeRequest
     */
    public function addMergeRequest(Feature $mergeRequest)
    {
        $this->mergeRequests[] = $mergeRequest;
    }

    /**
     * @return Feature[]
     */
    public function getMergeRequests()
    {
        return $this->mergeRequests;
    }
}