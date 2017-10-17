<?php

namespace Mirocode\GitReleaseMan\Tests\Command;

use Mirocode\GitReleaseMan\Command\BuildCommand;
use Mirocode\GitReleaseMan\Configuration;
use Mirocode\GitReleaseMan\Entity\Feature;
use Mirocode\GitReleaseMan\GitAdapter\GitAdapterAbstract;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Application;

class BuildCommandTest extends TestCase
{

    const DEFAULT_FEATURE_NAME = 'feature-123-my-cool-gear';

    public function testExecuteBuildTestStart()
    {
        $command       = $this->getBuildCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            'action'  => 'features-list',
        ), array('interactive' => false));

        $output = $commandTester->getDisplay();
        $this->assertContains('Features list', $output);
    }

    /**
     * @return BuildCommand
     */
    public function getBuildCommand()
    {
        $command = new BuildCommand();
        $command->setApplication(new Application());

        $configuration = new Configuration();
        /** @var GitAdapterAbstract|\PHPUnit_Framework_MockObject_MockObject $gitAdapter */
        $gitAdapter = $this->getMockForAbstractClass(GitAdapterAbstract::class, array($configuration));
        $gitAdapter->method('getFeaturesList')
                   ->willReturn(array(new Feature(self::DEFAULT_FEATURE_NAME)));
        $command->setGitAdapter($gitAdapter);

        return $command;
    }
}