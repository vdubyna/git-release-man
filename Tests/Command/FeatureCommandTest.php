<?php

namespace Mirocode\GitReleaseMan\Tests\Command;

use Mirocode\GitReleaseMan\Command\FeatureCommand;
use Mirocode\GitReleaseMan\Configuration;
use Mirocode\GitReleaseMan\Entity\Feature;
use Mirocode\GitReleaseMan\GitAdapter\GitAdapterAbstract;
use Mirocode\GitReleaseMan\Tests\GitAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Application;

class FeatureCommandTest extends TestCase
{
    const DEFAULT_FEATURE_NAME = 'feature-123-my-cool-gear';

    public function testExecuteFeatureStart()
    {
        $command = $this->getFeatureCommandWithNewFeature();
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            'action'  => 'start',
            '--name'  => self::DEFAULT_FEATURE_NAME,
        ), array('interactive' => false));

        $output = $commandTester->getDisplay();
        $this->assertContains('Start new feature', $output);

        $feature = $command->getFeature();
        $this->assertEquals(self::DEFAULT_FEATURE_NAME, $feature->getName());
        $this->assertEquals(Feature::STATUS_NEW, $feature->getStatus());
    }

    public function testExecuteFeatureClose()
    {
        $command = $this->getFeatureCommandWithNewFeature();
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            'action'  => 'close',
            '--name'  => 'feature-my-cool',
        ), array('interactive' => false));

        $output = $commandTester->getDisplay();
        $this->assertContains('Close feature', $output);

        $feature = $command->getFeature();
        $this->assertEquals('feature-my-cool', $feature->getName());
        $this->assertEquals(Feature::STATUS_CLOSE, $feature->getStatus());
    }

    public function testExecuteFeatureTest()
    {
        $command = $this->getFeatureCommandWithNewFeature();

        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            'action'  => 'test',
            '--name'  => 'feature-my-cool',
        ), array('interactive' => false));

        $output = $commandTester->getDisplay();
        $this->assertContains('Test feature', $output);

        $feature = $command->getFeature();
        $this->assertEquals('feature-my-cool', $feature->getName());
        $this->assertEquals(Feature::STATUS_TEST, $feature->getStatus());
    }

    /**
     * @return FeatureCommand
     */
    public function getFeatureCommandWithNewFeature()
    {
        $command = new FeatureCommand();
        $command->setApplication(new Application());
        $configuration = new Configuration();

        /** @var GitAdapterAbstract|\PHPUnit_Framework_MockObject_MockObject $gitAdapter */
        $gitAdapter = $this->getMockForAbstractClass(GitAdapterAbstract::class, array($configuration));

        $feature = new Feature(self::DEFAULT_FEATURE_NAME);
        $feature->setStatus(Feature::STATUS_NEW);

        $gitAdapter->expects($this->once())
                 ->method('loadFeature')
                 ->willReturn($feature);

        $command->setGitAdapter($gitAdapter);

        return $command;
    }
}