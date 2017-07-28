<?php

namespace Mirocode\GitReleaseMan\Entity;


class Branch implements BranchInterface
{
    protected $name;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        $this->name;
    }
}