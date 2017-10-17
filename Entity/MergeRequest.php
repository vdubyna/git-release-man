<?php

namespace Mirocode\GitReleaseMan\Entity;


class MergeRequest
{
    protected $number;

    protected $name;
    protected $url;
    protected $description;
    protected $isMergeable;
    protected $sourceBranch;
    protected $targetBranch;
    protected $commit;

    public function __construct($number)
    {
        $this->number = $number;
    }

    public function getNumber()
    {
        return $this->number;
    }

    /**
     * @param mixed $name
     *
     * @return MergeRequest
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $url
     *
     * @return MergeRequest
     */
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param mixed $description
     *
     * @return MergeRequest
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param mixed $number
     *
     * @return MergeRequest
     */
    public function setNumber($number)
    {
        $this->number = $number;

        return $this;
    }

    /**
     * @return boolean
     */
    public function getIsMergeable()
    {
        return $this->isMergeable;
    }

    /**
     * @param mixed $isMergeable
     *
     * @return MergeRequest
     */
    public function setIsMergeable($isMergeable)
    {
        $this->isMergeable = $isMergeable;

        return $this;
    }

    /**
     * @param mixed $sourceBranch
     *
     * @return MergeRequest
     */
    public function setSourceBranch($sourceBranch)
    {
        $this->sourceBranch = $sourceBranch;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getSourceBranch()
    {
        return $this->sourceBranch;
    }

    /**
     * @param mixed $targetBranch
     *
     * @return MergeRequest
     */
    public function setTargetBranch($targetBranch)
    {
        $this->targetBranch = $targetBranch;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getTargetBranch()
    {
        return $this->targetBranch;
    }

    /**
     * @param mixed $commit
     *
     * @return MergeRequest
     */
    public function setCommit($commit)
    {
        $this->commit = $commit;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getCommit()
    {
        return $this->commit;
    }


}