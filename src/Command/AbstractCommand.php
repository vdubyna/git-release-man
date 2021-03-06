<?php

namespace Mirocode\GitReleaseMan\Command;

use Mirocode\GitReleaseMan\Configuration;
use Mirocode\GitReleaseMan\GitAdapter\GitAdapterAbstract;
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
    protected $allowedActions = [];

    /**
     * @var GitReleaseManConfiguration
     */
    protected $configuration;

    /**
     * @var GitAdapterAbstract
     */
    protected $gitAdapter;

    /**
     * @var SymfonyStyle
     */
    protected $styleHelper;

    /**
     * @var bool
     */
    protected $forceExecute;

    /**
     * @return GitAdapterAbstract
     * @throws ExitException
     */
    public function getGitAdapter()
    {
        if (empty($this->gitAdapter)) {
            $gitAdapterName   = $this->getConfiguration()->getGitAdapterClassName();
            $this->gitAdapter = new $gitAdapterName($this->getConfiguration(), $this->getStyleHelper());
        }

        return $this->gitAdapter;
    }

    /**
     * @return Configuration
     * @throws ExitException
     */
    public function getConfiguration()
    {
        if (empty($this->configuration)) {
            $this->configuration = new GitReleaseManConfiguration();
        }

        return $this->configuration;
    }

    /**
     * @param GitAdapterAbstract $gitAdapter
     *
     * @return AbstractCommand
     */
    public function setGitAdapter(GitAdapterAbstract $gitAdapter)
    {
        $this->gitAdapter = $gitAdapter;

        return $this;
    }

    protected function configure()
    {
        $this->addOption('gitadapter', null, InputOption::VALUE_OPTIONAL, "Git Adapter")
            ->addOption('username', null, InputOption::VALUE_OPTIONAL, "Username")
            ->addOption('token', null, InputOption::VALUE_OPTIONAL, "Token")
            ->addOption('repository', null, InputOption::VALUE_OPTIONAL, "Repository name")
            ->addOption('force', null, InputOption::VALUE_OPTIONAL, "Force execution");

        parent::configure();
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     * @throws ExitException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->styleHelper = new SymfonyStyle($input, $output);
        $action            = $input->getArgument('action');
        if (!isset($this->allowedActions[$action])) {
            $allowedActions = implode(', ', array_keys($this->allowedActions));
            throw new ExitException("Action \"{$action}\" does not exist use one of the [{$allowedActions}]");
        }

        $methodName = $this->allowedActions[$action];

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

        if ($input->getOption('force')) {
            $this->forceExecute = (bool) $input->getOption('force');
        }

        if (key_exists($action, $this->allowedActions) && method_exists($this, $this->allowedActions[$action])) {
            try {
                $this->$methodName($input, $output);
            } catch (ExitException $e) {
                $output->writeln($e->getMessage());
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
     * @param      $message
     * @param null $default
     *
     * @return string
     * @throws ExitException
     */
    protected function askAndGetValueOrExit($message, $default = null)
    {
        $answer = $this->getStyleHelper()->ask($message, $default);
        if (!$answer) {
            throw new ExitException(ExitException::EXIT_MESSAGE . PHP_EOL);
        }

        return $answer;
    }

    /**
     * @param      $message
     * @param      $choices
     * @param null $default
     *
     * @return string
     * @throws ExitException
     */
    protected function askAndChooseValueOrExit($message, $choices, $default = null)
    {
        $answer = $this->getStyleHelper()->choice($message, $choices, $default);
        if (!$answer) {
            throw new ExitException(ExitException::EXIT_MESSAGE . PHP_EOL);
        }

        return $answer;
    }
}
