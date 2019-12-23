<?php

namespace Mirocode\GitReleaseMan\Entity;

class Feature
{
    const STATUS_NEW               = 'new';
    const STATUS_STARTED           = 'started';
    const STATUS_CLOSED            = 'closed';
    const STATUS_RELEASE_CANDIDATE = 'release-candidate';
    const STATUS_RELEASE_STABLE    = 'release-stable';

    protected $name;
    protected $status;
    protected $commit;
    protected $labels = [];
    protected $mergeRequest;

    /**
     * Feature constructor.
     *
     * @param $name
     */
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
     * @param array $labels
     *
     * @return Feature
     */
    public function setLabels(array $labels)
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
     * @param $label
     */
    public function addLabel($label)
    {
        $this->labels[] = $label;
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
