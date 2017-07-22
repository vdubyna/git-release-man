<?php
/**
 * Created by PhpStorm.
 * User: vdubyna
 * Date: 6/17/17
 * Time: 12:07
 */

namespace Mirocode\GitReleaseMan;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Mirocode\GitReleaseMan\ExitException as ExitException;
use Mirocode\GitReleaseMan\Configuration as GitReleaseManConfiguration;
use \Github\Client as GithubClient;

class AbstractCommand extends Command
{
    protected $allowedActions = array();

    protected $configuration;

    protected $apiClient;

    /**
     * @var GitAdapter
     */
    protected $gitAdapter;

    /**
     * @var SymfonyStyle
     */
    protected $styleHelper;

    /**
     * @return GitAdapter
     */
    public function getGitAdapter()
    {
        return $this->gitAdapter;
    }

    /**
     * @return Configuration
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * @param $githubKey
     *
     * @return GithubClient
     */
    public function getApiClient()
    {
        $githubKey = $this->getConfiguration()->getToken();
        if (empty($this->apiClient)) {
            $client = new GithubClient();
            $client->authenticate($githubKey, null, GithubClient::AUTH_HTTP_TOKEN);
            $this->apiClient = $client;
        }

        return $this->apiClient;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->configuration = new GitReleaseManConfiguration();
        $this->styleHelper = new SymfonyStyle($input, $output);
        $this->gitAdapter = new GithubAdapter($this->configuration);
        $action = $input->getArgument('action');
        $methodName = $this->allowedActions[$action];

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
     * @param string          $message
     *
     * @return void
     * @throws \Mirocode\GitReleaseMan\ExitException
     */
    protected function confirmOrExit($message)
    {
        if (!$this->getStyleHelper()->confirm($message)) {
            throw new ExitException(ExitException::EXIT_MESSAGE . PHP_EOL);
        }
    }

    /**
     * @return string
     * @throws \Mirocode\GitReleaseMan\ExitException
     */
    protected function askAndGetValueOrExit($message)
    {
        $answer = $this->getStyleHelper()->ask($message);
        if (!$answer) {
            throw new ExitException(ExitException::EXIT_MESSAGE . PHP_EOL);
        }

        return $answer;
    }
}