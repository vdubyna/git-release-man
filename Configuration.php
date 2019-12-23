<?php

namespace Mirocode\GitReleaseMan;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class Configuration
{
    protected $configuration;
    protected $repository;
    protected $username;
    protected $gitAdapter;
    protected $getGitAdapterEndpoint;
    protected $token;
    protected $releaseCandidateLabel;
    protected $releaseStableLabel;
    protected $featurePrefix;
    protected $masterBranch;
    protected $isDebug;

    const CONFIGURATION_FILENAME = '.git-release-man.yml';
    const DEFAULT_FEATURE_PREFIX = 'feature-';

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
                if (isset($configuration['gitadapter-endpoint'])) {
                    $this->setGitAdapterEndpoint($configuration['gitadapter-endpoint']);
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

                if (isset($configuration['master-branch'])) {
                    $this->releaseStableLabel = $configuration['master-branch'];
                }

                if (isset($configuration['feature-prefix'])) {
                    $this->featurePrefix = $configuration['feature-prefix'];
                }
                $this->isDebug = (isset($configuration['debug'])) ? (bool) $configuration['debug'] : false;
            }
        } catch (ParseException $e) {
            throw new ExitException("Unable to parse the YAML string: {$e->getMessage()}");
        }
    }

    /**
     * @return string
     */
    public function getConfigurationPath()
    {
        return getcwd() . '/' . self::CONFIGURATION_FILENAME;
    }

    public function getMasterBranch()
    {
        return (empty($this->masterBranch)) ? 'master' : $this->masterBranch;
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

    public function getFeaturePrefix()
    {
        return (empty($this->featurePrefix)) ? self::DEFAULT_FEATURE_PREFIX : $this->featurePrefix;
    }

    /**
     * @param        $gitAdapter
     * @param string $username
     * @param string $token
     * @param string $repository
     * @param string $gitAdapterEndpoint
     */
    public function initConfiguration($gitAdapter,
                                      $username = '',
                                      $token = '',
                                      $repository = '',
                                      $gitAdapterEndpoint = '')
    {
        $array = array(
            "gitadapter"              => $gitAdapter,
            "gitadapter-endpoint"     => $gitAdapterEndpoint,
            "master-branch"           => $this->getMasterBranch(),
            "feature-prefix"          => $this->getFeaturePrefix(),
            "release-candidate-label" => $this->getLabelForReleaseCandidate(),
            "release-stable-label"    => $this->getLabelForReleaseStable(),
        );

        $verifyArguments = [$username, $token, $repository, $gitAdapterEndpoint];
        array_walk($verifyArguments, function ($key) use ($array) {
            return (!$key) ?: array_push($array, $key);
        });

        $yaml = Yaml::dump(array_filter($array));
        file_put_contents($this->getConfigurationPath(), $yaml);
    }

    /**
     * @return bool
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
        $gitAdapter          = ucfirst(strtolower($this->getGitAdapter()));
        $gitAdapterClassName = "\\Mirocode\\GitReleaseMan\\GitAdapter\\{$gitAdapter}Adapter";
        if (!class_exists($gitAdapterClassName)) {
            throw new ExitException("GitAdapter {$gitAdapterClassName} does not exist.");
        }

        return $gitAdapterClassName;
    }

    /**
     * @param string $repository
     *
     * @return Configuration
     */
    public function setRepository($repository)
    {
        $this->repository = $repository;

        return $this;
    }

    /**
     * @param string $username
     *
     * @return Configuration
     */
    public function setUsername($username)
    {
        $this->username = $username;

        return $this;
    }

    /**
     * @param string $gitAdapter
     *
     * @return Configuration
     */
    public function setGitAdapter($gitAdapter)
    {
        $this->gitAdapter = $gitAdapter;

        return $this;
    }

    /**
     * @param string $token
     *
     * @return Configuration
     */
    public function setToken($token)
    {
        $this->token = $token;

        return $this;
    }

    /**
     * @return string
     */
    public function getGitAdapterEndpoint()
    {
        return $this->getGitAdapterEndpoint;
    }

    /**
     * @param string $gitAdapterEndpoint
     *
     * @return Configuration
     */
    public function setGitAdapterEndpoint($gitAdapterEndpoint)
    {
        $this->getGitAdapterEndpoint = $gitAdapterEndpoint;

        return $this;
    }

    public function isDebug()
    {
        return $this->isDebug;
    }
}
