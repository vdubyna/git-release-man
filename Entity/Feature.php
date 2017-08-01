<?php

namespace Mirocode\GitReleaseMan\Entity;

class Feature
{
    /**
     * @var string
     */
    protected $name;
    protected $status;
    protected $commit;
    protected $mergeRequestNumber;
    protected $mergeRequest;
    protected $labels = array();


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
     * @return Feature
     */
    public function setStatus($status)
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param mixed $commit
     *
     * @return Feature
     */
    public function setCommit($commit)
    {
        $this->commit = $commit;
        return $this;
    }

    /**
     * @return string
     */
    public function getCommit()
    {
        return $this->commit;
    }

    /**
     * @param integer $mergeRequestNumber
     *
     * @return Feature
     */
    public function setMergeRequestNumber($mergeRequestNumber)
    {
        $this->mergeRequestNumber = $mergeRequestNumber;
        return $this;
}

    /**
     * @return string
     */
    public function getMergeRequestNumber()
    {
        return $this->mergeRequestNumber;
    }

    /**
     * @param mixed $labels
     *
     * @return Feature
     */
    public function setLabels($labels)
    {
        $this->labels = $labels;

        return $this;
}

    /**
     * @return array
     */
    public function getLabels()
    {
        return $this->labels;
    }

    /**
     * @param MergeRequest $mergeRequest
     *
     * @return Feature
     */
    public function setMergeRequest(MergeRequest $mergeRequest)
    {
        $this->mergeRequest = $mergeRequest;

        return $this;
}

    /**
     * @return MergeRequest
     */
    public function getMergeRequest()
    {
        return $this->mergeRequest;
    }
}