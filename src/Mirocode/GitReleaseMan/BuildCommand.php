<?php

namespace Mirocode\GitReleaseMan;

use Mirocode\GitReleaseMan\AbstractCommand as Command;
use Symfony\Component\Console\Input\InputArgument;
use Mirocode\GitReleaseMan\ExitException as ExitException;

class BuildCommand extends Command
{
    protected $allowedActions = array(
        'init'                => 'init',
        'test'                => 'test',
        'release'             => 'release',
        'latest-release'      => 'latestRelease',
        'latest-test-release' => 'latestTestRelease',
    );

    protected function configure()
    {
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

        $username       = $this->askAndGetValueOrExit('What is your name?');
        $token          = $this->askAndGetValueOrExit('What is your token?');
        $repositoryName = $this->askAndGetValueOrExit('What is your repository name?');

        $this->getConfiguration()->initConfiguration($username, $token, $repositoryName);
    }

    public function test()
    {
        $this->getStyleHelper()->title("Create Release Candidate branch to do testing");
        $this->confirmOrExit('Do you want to create RC for testing?');

        $testLabel               = $this->getConfiguration()->getPRLabelForTest();
        $releaseCandidateVersion = $this->getGitAdapter()->getReleaseCandidateVersion();
        $pullRequests            = $this->getGitAdapter()->getPullRequestsByLabel($testLabel);

        if (empty($pullRequests)) {
            $this->getStyleHelper()->error('There is no pull requests ready for test.');
            throw new ExitException(ExitException::EXIT_MESSAGE . PHP_EOL);
        }

        $this->getGitAdapter()->createRemoteBranch($releaseCandidateVersion);
        $this->getStyleHelper()->success("Create Release Candidate \"{$releaseCandidateVersion}\" created");

        foreach ($pullRequests as $pullRequest) {
            $sourceBranch = $pullRequest['head']['ref'];
            $this->getGitAdapter()->mergeRemoteBranches($releaseCandidateVersion, $sourceBranch);
            $this->getStyleHelper()->success("Branch \"{$sourceBranch}\" merged");
        }
        // TODO Rollback process
        $this->getGitAdapter()->createTestReleaseTag($releaseCandidateVersion);

        $this->getStyleHelper()->success("New Release Candidate \"{$releaseCandidateVersion}\" is ready for testing");
    }

    public function release()
    {
        $this->getStyleHelper()->title("Merge ready for release branches and create Release TAG");
        $this->confirmOrExit('Do you want to continue and make release?');

        $releaseLabel = $this->getConfiguration()->getPRLabelForRelease();
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
            $this->getGitAdapter()->mergePullRequest($pullRequest['number']);
            $this->getStyleHelper()->success("Merge Pull Request {$pullRequest['title']} #{$pullRequest['number']}");
        }

        foreach ($pullRequests as $pullRequest) {
            $this->getGitAdapter()->removeRemoteBranch($pullRequest['head']['ref']);
            $this->getStyleHelper()->success("Branch {$pullRequest['head']['ref']} removed.");
        }

        $releaseVersion = $this->getGitAdapter()->getReleaseVersion();
        $this->getGitAdapter()->createReleaseTag($releaseVersion);

        foreach ($this->getGitAdapter()->getRCBranchesListByRelease($releaseVersion) as $rcBranch) {
            $this->getGitAdapter()->removeRemoteBranch($rcBranch);
        }

        $this->getStyleHelper()->success("Release \"{$releaseVersion}\" generated.");
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
