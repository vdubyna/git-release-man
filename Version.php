<?php

namespace Mirocode\GitReleaseMan;

/**
 * http://semver.org/
 *
 * Class Version
 * @package Mirocode\GitReleaseMan
 */
final class Version
{
    const VERSION_REGEX = '(?P<major>\d++)(?:\.(?P<minor>\d++))?(?:\.(?P<patch>\d++))?(?:[-.]?(?P<stability>beta|RC|alpha|stable)(?:[.-]?(?P<stabilityVersion>\d+)))?(?:[+]?(?P<metadata>.+))?';

    private static $stabilises = array(
        'alpha'  => 0,
        'beta'   => 1,
        'rc'     => 2,
        'stable' => 3,
    );

    private $major;
    private $minor;
    private $patch;
    private $stability;
    private $stabilityVersion;
    private $metadata;

    public function __construct($major, $minor = 0, $patch = 0, $stability = 'stable', $stabilityVersion = 0, $metaData = '')
    {
        if (!isset(self::$stabilises[$stability])) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Unknown stability "%s", accepts "%s" ', $stability,
                    implode('", "', array('alpha', 'beta', 'rc', 'stable', 'major', 'minor', 'patch'))
                )
            );
        }

        if (self::$stabilises[$stability] === 3 && !empty($metaData)) {
            throw new \InvalidArgumentException('Metadata cannot be set for stable.');
        }

        if (self::$stabilises[$stability] === 3 && $stabilityVersion > 0) {
            throw new \InvalidArgumentException('Version of the stability flag cannot be set for stable.');
        }

        $this->major            = $major;
        $this->minor            = $minor;
        $this->patch            = $patch;
        $this->stability        = $stability;
        $this->stabilityVersion = $stabilityVersion;
        $this->metadata         = $metaData;
    }

    public static function fromString($version)
    {
        if (preg_match('/^v?' . self::VERSION_REGEX . '$/i', $version, $matches)) {
            $stability = strtolower(isset($matches['stability']) ? $matches['stability'] : 'stable');
            if (!isset(self::$stabilises[$stability])) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Unknown stability "%s", accepts "%s" ', $stability,
                        implode('", "', array('alpha', 'beta', 'rc', 'stable', 'major', 'minor', 'patch'))
                    )
                );
            }

            return new self(
                (int)$matches['major'],
                (int)(isset($matches['minor'])) ? $matches['minor'] : 0,
                (int)(isset($matches['patch'])) ? $matches['patch'] : 0,
                $stability,
                (int)(isset($matches['stabilityVersion'])) ? $matches['stabilityVersion'] : 0,
                (isset($matches['metadata'])) ? $matches['metadata'] : '');
        } else {
            throw new \InvalidArgumentException(
                sprintf(
                    'Unable to parse version "%s" Expects an SemVer compatible version. ' .
                    'Eg. "1.0.0", "1.0", "1.0" or "1.0.0-beta1", "1.0.0-beta-1"',
                    $version
                )
            );
        }
    }

    public function getVersion()
    {
        $version = '';
        $version .= "{$this->getMajor()}";
        $version .= ".{$this->getMinor()}";
        $version .= ".{$this->getPatch()}";
        if (self::$stabilises[$this->stability] < 3) {
            $version .= "-{$this->getStability()}{$this->getStabilityVersion()}";
        }
        if ($this->metadata) {
            $version .= "+{$this->getMetadata()}";
        }

        return $version;
    }

    public function getVersionPrefix()
    {
        return 'v';
    }

    public function getMajor()
    {
        return $this->major;
    }

    public function getMinor()
    {
        return $this->minor;
    }

    public function getPatch()
    {
        return $this->patch;
    }

    public function getMetadata()
    {
        return $this->metadata;
    }

    public function getStability()
    {
        return strtoupper($this->stability);
    }

    public function getStabilityVersion()
    {
        return $this->stabilityVersion;
    }

    public function increase($stability, $metaData = '')
    {
        switch ($stability) {
            case 'patch':
                return new self($this->major, $this->minor, $this->patch + 1);
            case 'minor':
                return new self($this->major, $this->minor + 1, 0);
            case 'major':
                return new self($this->major + 1, 0, 0);
            case 'alpha':
            case 'beta':
            case 'rc':
                if (self::$stabilises[$this->stability] === 3) {
                    return new self($this->major, $this->minor, $this->patch, $stability, 1, $metaData);
                } else {
                    return new self($this->major, $this->minor, $this->patch, $stability,
                        $this->stabilityVersion + 1, $metaData);
                }
            case 'stable':
                if ($this->major === 0) {
                    return new self(1, 0, 0);
                } else {
                    return new self($this->major, $this->minor, $this->patch);
                }
            default:
                throw new \InvalidArgumentException(
                    sprintf(
                        'Unknown stability "%s", accepts "%s" ', $stability,
                        implode('", "', array('alpha', 'beta', 'rc', 'stable', 'major', 'minor', 'patch'))
                    )
                );
        }
    }

    public function __toString()
    {
        return $this->getVersion();
    }

    /**
     * @return bool
     */
    public function isStable()
    {
        return (self::$stabilises[$this->stability] === 3);
    }
}