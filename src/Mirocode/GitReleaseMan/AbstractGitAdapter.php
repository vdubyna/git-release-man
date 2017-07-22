<?php
/**
 * Created by PhpStorm.
 * User: vdubyna
 * Date: 7/21/17
 * Time: 15:15
 */

namespace Mirocode\GitReleaseMan;

use \Mirocode\GitReleaseMan\Configuration;

abstract class AbstractGitAdapter implements GitAdapter
{
    protected $configuration;

    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * @return \Mirocode\GitReleaseMan\Configuration
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }
}