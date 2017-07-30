<?php

namespace Mirocode\GitReleaseMan\Tests\Command;

use Mirocode\GitReleaseMan\Command\FeatureCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Application;

class FeatureCommandTest extends TestCase
{
    public function testExecute()
    {
        $command = new FeatureCommand();
        $command->setApplication(new Application());

        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command' => $command->getName(),
            'action'  => 'list',
        ));

        $output = $commandTester->getDisplay();
        $this->assertContains('Features list', $output);
    }
}