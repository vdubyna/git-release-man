<?php

namespace Mirocode\GitReleaseMan\Entity;


interface PullRequestInterface
{
    public function getName();
    public function getNumber();
    public function getUrl();
    public function getDescription();
}