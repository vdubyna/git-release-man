<?php
/**
 * Created by PhpStorm.
 * User: vdubyna
 * Date: 7/14/17
 * Time: 15:50
 */

namespace Mirocode\GitReleaseMan;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class Configuration
{
    protected $configuration;

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
                $this->configuration = Yaml::parse(file_get_contents($filePath));
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

    public function getRepositoryName()
    {
        return $this->configuration['repository'];
    }

    public function getUsername()
    {
        return $this->configuration['username'];
    }

    public function getToken()
    {
        return $this->configuration['token'];
    }

    public function getPRLabelForTest()
    {
        return 'IN-BETA';
    }

    public function getPRLabelForRelease()
    {
        return 'OK-PROD';
    }

    /**
     * @param $username
     * @param $token
     * @param $repository
     */
    public function initConfiguration($username, $token, $repository) {
        $array = array(
            "username"   => $username,
            "token"      => $token,
            "repository" => $repository,
        );

        $yaml = Yaml::dump($array);
        file_put_contents($this->getConfigurationPath(), $yaml);
    }

    /**
     * @return bool
     */
    public function isConfigurationExists()
    {
        return is_file($this->getConfigurationPath());
    }
}