<?php

namespace Mirocode\GitReleaseMan\Tests;

use Mirocode\GitReleaseMan\Entity\Feature;
use Mirocode\GitReleaseMan\ExitException;
use Mirocode\GitReleaseMan\GitAdapter\GitlocalAdapter;
use Mirocode\GitReleaseMan\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\StyleInterface;

class GitlocalAdapterTest extends TestCase
{
    /**
     * @throws ExitException
     */
    public function testGetFeaturesList()
    {
        $styleHelper = $this->getMockBuilder(StyleInterface::class)->getMock();
        $configuration = $this->getMockBuilder(Configuration::class)->getMock();
        $configuration->method('getFeaturePrefix')
            ->willReturn('feature-');

        $gitLocalAdapter = $this->getMockBuilder(GitlocalAdapter::class)
                     ->setConstructorArgs([$configuration, $styleHelper])
                     ->setMethods(['execShellCommand', 'buildFeature'])
                     ->getMock();

        $gitLocalAdapter->method('execShellCommand')
            ->willReturn(
<<<OUTPUT
* feature-ABC
  feature-ASD
  feature-QWE
OUTPUT
            );

        $featuresMap = [
            ['feature-ABC', $this->getMockBuilder(Feature::class)->setConstructorArgs(['feature-ABC'])->setMethodsExcept(['getName'])->getMock()],
            ['feature-ASD', $this->getMockBuilder(Feature::class)->setConstructorArgs(['feature-ASD'])->setMethodsExcept(['getName'])->getMock()],
            ['feature-QWE', $this->getMockBuilder(Feature::class)->setConstructorArgs(['feature-QWE'])->setMethodsExcept(['getName'])->getMock()],
        ];

        $gitLocalAdapter->method('buildFeature')->will($this->returnValueMap($featuresMap));

        /** @var GitlocalAdapter $gitLocalAdapter */
        $features = $gitLocalAdapter->getFeaturesList();
        foreach (['feature-ABC', 'feature-ASD', 'feature-QWE'] as $featureName) {
            $feature = array_shift($features);
            $this->assertEquals($featureName, $feature->getName());
        }
    }

    public function testBuildFeature()
    {
        $styleHelper = $this->getMockBuilder(StyleInterface::class)->getMock();
        $configuration = $this->getMockBuilder(Configuration::class)->getMock();
        $configuration->method('getFeaturePrefix')
                      ->willReturn('feature-');

        $gitLocalAdapter = $this->getMockBuilder(GitlocalAdapter::class)
                                ->setConstructorArgs([$configuration, $styleHelper])
                                ->getMock();

        $feature = $gitLocalAdapter->buildFeature('feature-ASD');

        $this->assertInstanceOf('Mirocode\GitReleaseMan\Entity\Feature', $feature);
        $this->assertEquals('feature-ASD', $feature->getName());

    }

}
