<?php

namespace Mirocode\GitReleaseMan\Tests;

use Mirocode\GitReleaseMan\Version;
use PHPUnit\Framework\TestCase;

class VersionTest extends TestCase
{
    public function testFromStringIsObject()
    {
        $versionObject = Version::fromString('v1.0.0');
        $this->assertInstanceOf('Mirocode\GitReleaseMan\Version', $versionObject);
    }

    public function validVersionsDataProvider()
    {
        return array(
            array('1.0.0', 'v1'),
            array('1.1.0', 'v1.1'),
            array('0.1.3', 'v0.1.3'),
            array('0.1.3', 'v0.1.3'),
            array('1.2.3', 'v1.2.3'),
            array('1.2.3-RC1', 'v1.2.3-RC1'),
            array('1.2.3-ALPHA1', 'v1.2.3-alpha.1'),
            array('1.2.3-BETA1', 'v1.2.3-beta1'),
            array('1.2.3-RC1+2017-07-12', 'v1.2.3-RC1+2017-07-12'),
            array('1.2.3-RC1+2017-07-12', 'v1.2.3-RC-1+2017-07-12'),
        );
    }

    /**
     * @dataProvider validVersionsDataProvider
     */
    public function testVersionGetVersionString($expected, $version)
    {
        $versionObject = Version::fromString($version);
        $this->assertEquals($expected, $versionObject->getVersion());
    }

    public function testVersionGetPrefixOfTheVersion()
    {
        $versionObject = Version::fromString('v1.0.0');
        $this->assertEquals('v', $versionObject->getVersionPrefix());
    }

    public function testVersionGetMajorVersion()
    {
        $versionObject = Version::fromString('v1.0.0');
        $this->assertEquals('1', $versionObject->getMajor());
    }

    public function testVersionGetMinorVersion()
    {
        $versionObject = Version::fromString('v1.2.3');
        $this->assertEquals('2', $versionObject->getMinor());
    }

    public function testVersionGetPatchVersion()
    {
        $versionObject = Version::fromString('v1.2.3');
        $this->assertEquals('3', $versionObject->getPatch());
    }

    public function testVersionGetMetadata()
    {
        $versionObject = Version::fromString('v1.2.3-RC1+2017-01-12');
        $this->assertEquals('2017-01-12', $versionObject->getMetadata());
    }

    public function testVersionGetStability()
    {
        $versionObject = Version::fromString('v1.2.3-RC1');
        $this->assertEquals('RC', $versionObject->getStability());
    }

    public function testVersionGetStabilityVersion()
    {
        $versionObject = Version::fromString('v1.2.3-RC5');
        $this->assertEquals('5', $versionObject->getStabilityVersion());
    }

    public function increaseDataProvider()
    {
        return array(
            array('1.0.0', '0.0.1', 'stable'),
            array('1.0.0', 'v1', 'stable'),

            array('1.1.0-RC1', 'v1.1', 'rc'),
            array('1.1.0-BETA1', 'v1.1', 'beta'),
            array('1.1.0-ALPHA1', 'v1.1', 'alpha'),

            array('1.0.0', 'v0.1.3', 'major'),
            array('0.2.0', 'v0.1.3', 'minor'),
            array('1.2.4', 'v1.2.3', 'patch'),
            array('1.2.4', 'v1.2.3-RC1', 'patch'),
            array('1.2.4', 'v1.2.3-alpha.1', 'patch'),

            array('1.2.3', 'v1.2.3-RC1', 'stable'),
            array('1.2.3-RC2', 'v1.2.3-RC1', 'rc'),
            array('1.2.3-ALPHA2', 'v1.2.3-alpha.1', 'alpha'),
            array('1.2.3-BETA2', 'v1.2.3-beta1', 'beta'),
            array('1.2.3', 'v1.2.3-RC1+2017-07-12', 'stable'),
            array('2.0.0', 'v1.2.3-RC1+2017-07-12', 'major'),
            array('1.3.0', 'v1.2.3-RC1+2017-07-12', 'minor'),
            array('1.2.3-RC2', 'v1.2.3-RC-1+2017-07-12', 'rc'),
        );
    }

    /**
     * @dataProvider increaseDataProvider
     *
     * @param $expected
     * @param $version
     */
    public function testIncrease($expected, $version, $stability)
    {
        $versionObject = Version::fromString($version);
        $this->assertEquals($expected, $versionObject->increase($stability)->__toString());
    }

    public function testInvalidStringException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $versionObject = Version::fromString('invalid_version');
    }

    public function testInvalidStabilityTypeException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $versionObject = new Version(1,2,3,'invalid_stability', 1);
    }

    public function testInvalidMetaversionException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $versionObject = new Version(1,2,3,'stable', 1);
    }

    public function testInvalidIncreaseStabilityException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $versionObject = Version::fromString('1.3.1');
        $versionObject->increase('invalid_stability');
    }

    public function testVersionGetIsStable()
    {
        $versionObject = Version::fromString('v1.2.3');
        $this->assertEquals(true, $versionObject->isStable());

        $versionObject = Version::fromString('v1.2.3-RC1');
        $this->assertEquals(false, $versionObject->isStable());
    }
}