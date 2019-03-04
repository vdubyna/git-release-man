<?php

namespace Mirocode\GitReleaseMan\Tests\Command;

use Mirocode\GitReleaseMan\Command\FeatureCommand;
use Mirocode\GitReleaseMan\Configuration;
use Mirocode\GitReleaseMan\Entity\Feature;
use Mirocode\GitReleaseMan\GitAdapter\GitAdapterAbstract;
use Mirocode\GitReleaseMan\GitAdapter\GitlocalAdapter;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Application;

class FeatureCommandTest extends \Codeception\Test\Unit
{
    /**
     * @throws \Mirocode\GitReleaseMan\ExitException
     * @throws \Exception
     */
    public function testExecuteFeatureStart()
    {
        $command = $this->getFeatureCommand();
        $output = new StreamOutput(fopen('php://memory', 'w', false));
        $input = new ArrayInput([
            'command' => $command->getName(),
            'action'  => 'start',
            '--name'  => 'feature-123-my-cool-gear',
            '--debug'  => 'true',
        ]);
        $input->setInteractive(false);
        $input->setStream(self::createStream([]));

        $command->setStyleHelper(new SymfonyStyle($input, $output));

        $configuration = new Configuration();

        $feature = new Feature('feature-123-my-cool-gear');
        $feature->setStatus(Feature::STATUS_NEW);

        /** @var GitlocalAdapter $gitAdapter */
        $gitAdapter = $this->construct(GitlocalAdapter::class, [$configuration, $command->getStyleHelper()],
            ['getFeature' => $feature, 'buildFeature' => $feature]);

        $command->setGitAdapter($gitAdapter);

        $command->run($input, $output);
        $commandOutput = $this->getDisplay($output);
        $this->assertContains('Start feature', $commandOutput);

        $feature = $command->getFeature();
        $this->assertEquals('feature-123-my-cool-gear', $feature->getName());
        $this->assertEquals(Feature::STATUS_NEW, $feature->getStatus());
    }

    /**
     * @throws \Mirocode\GitReleaseMan\ExitException
     */
    public function testExecuteFeatureClose()
    {
        $command = $this->getFeatureCommand();
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
        $this->assertEquals(Feature::STATUS_CLOSED, $feature->getStatus());
    }

    /**
     * @throws \Mirocode\GitReleaseMan\ExitException
     */
    public function testExecuteFeatureTest()
    {
        $command = $this->getFeatureCommand();

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
        $this->assertEquals(Feature::STATUS_RELEASE_CANDIDATE, $feature->getStatus());
    }

    /**
     * @return FeatureCommand
     */
    public function getFeatureCommand()
    {
        $command = new FeatureCommand();
        $command->setApplication(new Application());

        return $command;
    }

    /**
     * Gets the display returned by the last execution of the command.
     *
     * @param bool $normalize Whether to normalize end of lines to \n or not
     *
     * @return string The display
     */
    public function getDisplay(StreamOutput $output, $normalize = false)
    {
        rewind($output->getStream());

        $display = stream_get_contents($output->getStream());

        if ($normalize) {
            $display = str_replace(PHP_EOL, "\n", $display);
        }

        return $display;
    }

    private static function createStream(array $inputs)
    {
        $stream = fopen('php://memory', 'r+', false);

        fwrite($stream, implode(PHP_EOL, $inputs));
        rewind($stream);

        return $stream;
    }
}