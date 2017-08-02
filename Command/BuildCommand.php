<?php

namespace Mirocode\GitReleaseMan\Command;

use Mirocode\GitReleaseMan\Command\AbstractCommand as Command;
use Mirocode\GitReleaseMan\Entity\Feature;
use Symfony\Component\Console\Input\InputArgument;
use Mirocode\GitReleaseMan\ExitException as ExitException;

class BuildCommand extends Command
{
    protected $allowedActions = array(
        'init'                => 'init',
        'test'                => 'test',
        'release'             => 'release',
        'features-list'       => 'featuresList',
        'latest-release'      => 'latestRelease',
        'latest-test-release' => 'latestTestRelease',
    );

    protected function configure()
    {
        parent::configure();
        $this->setName('git-release:build')
             ->addArgument('action', InputArgument::REQUIRED, 'Action')
             ->setDescription('Init git release man')
             ->setHelp('Init git release man');
    }

    public function init()
    {
        $confirmationMassage = ($this->getConfiguration()->isConfigurationExists())
            ? "Configuration already exists in current repository?"
            : "Do you want to init git-release-man configuration in current repository?";
        $this->confirmOrExit($confirmationMassage);

        $gitAdapter = ($this->getConfiguration()->getGitAdapter())
            ? $this->getConfiguration()->getGitAdapter()
            : $this->askAndChooseValueOrExit(
                'What is your gitAdapter (github|bitbucket)?', array('github', 'bitbucket', 'gitlab'));

        $username = ($this->getConfiguration()->getUsername())
            ? $this->getConfiguration()->getUsername()
            : $this->askAndGetValueOrExit('What is your name?');

        $token = ($this->getConfiguration())
            ? ($this->getConfiguration()->getToken())
            : $this->askAndGetValueOrExit('What is your token?');

        $repositoryName = ($this->getConfiguration()->getRepository())
            ? $this->getConfiguration()->getRepository()
            : $this->askAndGetValueOrExit('What is your repository name?');

        $this->getConfiguration()->initConfiguration($username, $token, $repositoryName, $gitAdapter);
    }

    public function test()
    {
        $this->getStyleHelper()->title("Create Release Candidate branch to do testing");
        $this->confirmOrExit('Do you want to create RC for testing?');

        $testLabel    = $this->getConfiguration()
                             ->getLabelForTest();
        $pullRequests = $this->getGitAdapter()
                             ->getMergeRequestsByLabel($testLabel);

        if (empty($pullRequests)) {
            $this->getStyleHelper()->error('There is no pull requests ready for test.');
            throw new ExitException(ExitException::EXIT_MESSAGE . PHP_EOL);
        }

        $testVersion = $this->getGitAdapter()->getTestVersion();
        $this->getGitAdapter()->buildFeature($testVersion);
        $this->getStyleHelper()->success("Create Release Candidate \"{$testVersion}\" created");

        foreach ($pullRequests as $pullRequest) {
            $sourceBranch = $pullRequest['head']['ref'];
            $this->getGitAdapter()->mergeRemoteBranches($testVersion, $sourceBranch);
            $this->getStyleHelper()->success("Branch \"{$sourceBranch}\" merged");
        }
        // TODO Rollback process
        $this->getGitAdapter()->createTestReleaseTag($testVersion);

        $this->getStyleHelper()->success("New Release Candidate \"{$testVersion}\" is ready for testing");
    }

    public function release()
    {
        $this->getStyleHelper()->title("Merge ready for release branches and create Release TAG");
        $this->confirmOrExit('Do you want to continue and make release?');

        $releaseLabel = $this->getConfiguration()->getLabelForRelease();
        $pullRequests = $this->getGitAdapter()->getPullRequestsByLabel($releaseLabel);

        if (empty($pullRequests)) {
            $this->getStyleHelper()->error('There is no Pull requests ready for release');
            throw new ExitException(ExitException::EXIT_MESSAGE . PHP_EOL);
        }

        foreach ($pullRequests as $pullRequest) {
            if (empty($pullRequest['mergeable'])) {
                $this->getStyleHelper()->error("Pull Request #{$pullRequest['number']} can not be merged. " .
                    "Verify and solve conflicts: {$pullRequest['html_url']}");
                throw new ExitException(ExitException::EXIT_MESSAGE . PHP_EOL);
            }
        }

        $this->getStyleHelper()->success("There is no conflicts, Pull Requests ready for release");

        foreach ($pullRequests as $pullRequest) {
            $this->getGitAdapter()->mergeMergeRequest($pullRequest['number']);
            $this->getStyleHelper()->success("Merge Pull Request {$pullRequest['title']} #{$pullRequest['number']}");
        }

        foreach ($pullRequests as $pullRequest) {
            $this->getGitAdapter()->removeFeature($pullRequest['head']['ref']);
            $this->getStyleHelper()->success("Branch {$pullRequest['head']['ref']} removed.");
        }

        $releaseVersion = $this->getGitAdapter()->getReleaseVersion();
        $this->getGitAdapter()->createReleaseTag($releaseVersion);

        foreach ($this->getGitAdapter()->getRCBranchesListByRelease($releaseVersion) as $rcBranch) {
            $this->getGitAdapter()->removeFeature($rcBranch);
        }

        $this->getStyleHelper()->success("Release \"{$releaseVersion}\" generated.");
    }

    /**
     * List available features
     */
    public function featuresList()
    {
        $features = $this->getGitAdapter()->getFeaturesList();
        $headers  = array('Feature Name', 'Merge Request');

        $rows = array_map(function (Feature $feature) {
            $mergeRequest = $this->getGitAdapter()->getMergeRequestByFeature($feature);

            if (!empty($mergeRequest)) {
                $mergeRequestMessage = "Merge Request: #{$mergeRequest->getNumber()} - {$mergeRequest->getName()}\n" .
                    "{$mergeRequest->getUrl()}";
            } else {
                $mergeRequestMessage = "There is no open MergeRequest";
            }

            return array(
                $feature->getName(),
                $mergeRequestMessage,
            );
        }, $features);

        $this->getStyleHelper()->section("Features list");
        $this->getStyleHelper()->table($headers, $rows);
    }

    public function latestRelease()
    {
        $latestReleaseTag = $this->getGitAdapter()->getLatestReleaseTag();
        $this->getStyleHelper()->write($latestReleaseTag);
    }

    public function latestTestRelease()
    {
        $latestTestReleaseTag = $this->getGitAdapter()->getLatestTestReleaseTag();
        $this->getStyleHelper()->write($latestTestReleaseTag);
    }
}
