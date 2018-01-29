<?php

namespace Mirocode\GitReleaseMan;

use Mirocode\GitReleaseMan\GitAdapter\GithubAdapter;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class Configuration
{
    protected $configuration;
    protected $repository;
    protected $username;
    protected $gitAdapter;
    protected $token;
    protected $releaseCandidateLabel;
    protected $releaseStableLabel;

    const CONFIGURATION_FILENAME = '.git-release-man.yml';

    const DEFAULT_VERSION = '1.0.0';

    /**
     * Configuration constructor.
     *
     * @param $filePath
     *
     * @throws ExitException
     */
    public function __construct($filePath = '')
    {
        try {
            if (empty($filePath)) {
                $filePath = $this->getConfigurationPath();
            }
            if (is_file($filePath)) {
                $configuration = Yaml::parse(file_get_contents($filePath));

                // TODO verify values
                if (isset($configuration['gitadapter'])) {
                    $this->setGitAdapter($configuration['gitadapter']);
                }
                if (isset($configuration['username'])) {
                    $this->setUsername($configuration['username']);
                }
                if (isset($configuration['token'])) {
                    $this->setToken($configuration['token']);
                }
                if (isset($configuration['repository'])) {
                    $this->setRepository($configuration['repository']);
                }

                if (isset($configuration['release-candidate-label'])) {
                    $this->releaseCandidateLabel = $configuration['release-candidate-label'];
                }

                if (isset($configuration['release-stable-label'])) {
                    $this->releaseStableLabel = $configuration['release-stable-label'];
                }
            }
        } catch (ParseException $e) {
            throw new ExitException("Unable to parse the YAML string: {$e->getMessage()}");
        }
    }

    /**
     * @return string
     * @throws \Mirocode\GitReleaseMan\ExitException
     */
    public function getConfigurationPath()
    {
        return getcwd() . '/' . self::CONFIGURATION_FILENAME;
    }

    public function getMasterBranch()
    {
        return 'master';
    }

    public function getRepository()
    {
        return $this->repository;
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function getToken()
    {
        return $this->token;
    }

    public function getGitAdapter()
    {
        return $this->gitAdapter;
    }

    public function getLabelForReleaseCandidate()
    {
        return (empty($this->releaseCandidateLabel)) ? 'RELEASE-CANDIDATE' : $this->releaseCandidateLabel;
    }

    public function getLabelForReleaseStable()
    {
        return (empty($this->releaseStableLabel)) ? 'RELEASE-STABLE' : $this->releaseStableLabel;
    }

    /**
     * @param $username
     * @param $token
     * @param $repository
     *
     * @throws ExitException
     */
    public function initConfiguration($username, $token, $repository, $gitAdapter) {
        $array = array(
            "gitadapter" => $gitAdapter,
            "username"   => $username,
            "token"      => $token,
            "repository" => $repository,
        );

        $yaml = Yaml::dump($array);
        file_put_contents($this->getConfigurationPath(), $yaml);
    }

    /**
     * @return bool
     * @throws ExitException
     */
    public function isConfigurationExists()
    {
        return is_file($this->getConfigurationPath());
    }

    /**
     * @return string
     * @throws ExitException
     */
    public function getGitAdapterClassName()
    {
        $gitAdapter = ucfirst(strtolower($this->getGitAdapter()));
        $gitAdapterClassName = "\\Mirocode\\GitReleaseMan\\GitAdapter\\{$gitAdapter}Adapter";
        if (!class_exists($gitAdapterClassName)) {
            throw new ExitException("GitAdapter {$gitAdapterClassName} does not exist.");
        }

        return $gitAdapterClassName;
    }

    /**
     * @param mixed $repository
     *
     * @return Configuration
     */
    public function setRepository($repository)
    {
        $this->repository = $repository;

        return $this;
    }

    /**
     * @param mixed $username
     *
     * @return Configuration
     */
    public function setUsername($username)
    {
        $this->username = $username;

        return $this;
}

    /**
     * @param mixed $gitAdapter
     *
     * @return Configuration
     */
    public function setGitAdapter($gitAdapter)
    {
        $this->gitAdapter = $gitAdapter;

        return $this;
    }

    /**
     * @param mixed $token
     *
     * @return Configuration
     */
    public function setToken($token)
    {
        $this->token = $token;

        return $this;
    }
}