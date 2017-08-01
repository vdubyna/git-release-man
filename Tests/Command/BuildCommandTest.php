<?php

namespace Mirocode\GitReleaseMan\Tests\Command;

use Mirocode\GitReleaseMan\Command\BuildCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Application;

class BuildCommandTest extends TestCase
{
    public function testExecuteInitCommand()
    {
        $command = new BuildCommand();
        $command->setApplication(new Application());

        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            'action'  => 'init',
            '--gitadapter'   => 'github',
            '--username'     => 'my-username',
            '--token'        => 'my-token',
            '--repository'   => 'check-repository',
            '--no-questions' => 'true',
        ));

        $this->assertEquals('github', $command->getConfiguration()->getGitAdapter());
        $this->assertEquals('my-username', $command->getConfiguration()->getUsername());
        $this->assertEquals('my-token', $command->getConfiguration()->getToken());
        $this->assertEquals('check-repository', $command->getConfiguration()->getRepository());
    }

    public function tearDown()
    {
        unlink(__DIR__ . '/.git-release-man.yml');
    }
}