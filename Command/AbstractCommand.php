<?php

namespace Mirocode\GitReleaseMan\Command;

use Mirocode\GitReleaseMan\Configuration;
use Mirocode\GitReleaseMan\GitAdapter\GitAdapterInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Mirocode\GitReleaseMan\ExitException as ExitException;
use Mirocode\GitReleaseMan\Configuration as GitReleaseManConfiguration;

abstract class AbstractCommand extends Command
{
    protected $allowedActions = array();

    protected $configuration;

    /**
     * @var GitAdapterInterface
     */
    protected $gitAdapter;

    /**
     * @var SymfonyStyle
     */
    protected $styleHelper;
    protected $noQuestions;

    /**
     * @return GitAdapterInterface
     */
    public function getGitAdapter()
    {
        if (empty($this->gitAdapter)) {
            $gitAdapterName   = $this->getConfiguration()->getGitAdapterClassName();
            $this->gitAdapter = new $gitAdapterName($this->getConfiguration());
        }

        return $this->gitAdapter;
    }

    /**
     * @return Configuration
     */
    public function getConfiguration()
    {
        if (empty($this->configuration)) {
            $this->configuration = new GitReleaseManConfiguration();
        }

        return $this->configuration;
    }

    /**
     * @param mixed $noQuestions
     *
     * @return AbstractCommand
     */
    public function setNoQuestions($noQuestions)
    {
        $this->noQuestions = $noQuestions;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isNoQuestionsEnabled()
    {
        return $this->noQuestions;
    }

    protected function configure()
    {
        $this->addOption('gitadapter', null, InputOption::VALUE_OPTIONAL, "Git Adapter")
            ->addOption('username', null, InputOption::VALUE_OPTIONAL, "Username")
            ->addOption('token', null, InputOption::VALUE_OPTIONAL, "Token")
            ->addOption('repository', null, InputOption::VALUE_OPTIONAL, "Repository name")
            ->addOption('no-questions', 'nq', InputOption::VALUE_OPTIONAL, "Do not ask any interactive question");

        // TODO add configuration file option

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->styleHelper = new SymfonyStyle($input, $output);
        $action            = $input->getArgument('action');
        $methodName        = $this->allowedActions[$action];

        if ($input->getOption('gitadapter')) {
            $this->getConfiguration()->setGitAdapter($input->getOption('gitadapter'));
        }
        if ($input->getOption('username')) {
            $this->getConfiguration()->setUsername($input->getOption('username'));
        }
        if ($input->getOption('token')) {
            $this->getConfiguration()->setToken($input->getOption('token'));
        }
        if ($input->getOption('repository')) {
            $this->getConfiguration()->setRepository($input->getOption('repository'));
        }
        if ($input->getOption('no-questions')) {
            $this->setNoQuestions(true);
        }

        if (key_exists($action, $this->allowedActions) && method_exists($this, $this->allowedActions[$action])) {
            try {
                $this->$methodName($input, $output);
            } catch (ExitException $e) {
                $output->write($e->getMessage());
            }
        }
    }

    /**
     *
     * @param                 $cmd
     *
     * @return string
     */
    protected function executeShellCommand($cmd)
    {
        $process = new Process($cmd);
        $process->mustRun();

        return trim($process->getOutput());
    }

    /**
     * @return SymfonyStyle
     */
    public function getStyleHelper()
    {
        return $this->styleHelper;
    }

    /**
     * @param string $message
     *
     * @return void
     * @throws ExitException
     */
    protected function confirmOrExit($message)
    {
        if (!$this->getStyleHelper()->confirm($message)) {
            throw new ExitException(ExitException::EXIT_MESSAGE . PHP_EOL);
        }
    }

    /**
     * @return string
     * @throws ExitException
     */
    protected function askAndGetValueOrExit($message)
    {
        $answer = $this->getStyleHelper()->ask($message);
        if (!$answer) {
            throw new ExitException(ExitException::EXIT_MESSAGE . PHP_EOL);
        }

        return $answer;
    }

    /**
     * @return string
     * @throws ExitException
     */
    protected function askAndChooseValueOrExit($message, $choices)
    {
        $answer = $this->getStyleHelper()->choice($message, $choices);
        if (!$answer) {
            throw new ExitException(ExitException::EXIT_MESSAGE . PHP_EOL);
        }

        return $answer;
    }
}