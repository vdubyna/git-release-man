<?php

namespace Mirocode\GitReleaseMan\Tests\Command;

use Mirocode\GitReleaseMan\Command\BuildCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Application;

class BuildCommandTest extends TestCase
{

    public function testExecuteBuildTestStart()
    {
        $command = $this->getBuildCommand();
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            'action'  => 'test',
        ), array('interactive' => false));

        $output = $commandTester->getDisplay();
        $this->assertContains('Start new feature', $output);
    }

    /**
     * @return BuildCommand
     */
    public function getBuildCommand()
    {
        $command = new BuildCommand();
        $command->setApplication(new Application());

        return $command;
    }
}