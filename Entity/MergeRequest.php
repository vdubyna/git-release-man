<?php

namespace Mirocode\GitReleaseMan\Entity;


class MergeRequest
{
    protected $number;

    public function __construct($number)
    {
        $this->number = $number;
    }

    /**
     * @return string
     */
    public function getName()
    {
        // TODO: Implement getNumber() method.
    }

    public function getNumber()
    {
        return $this->getNumber();
    }

    public function getUrl()
    {
        // TODO: Implement getUrl() method.
    }

    public function getDescription()
    {
        // TODO: Implement getDescription() method.
    }
}