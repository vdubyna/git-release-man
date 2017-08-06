<?php

namespace Mirocode\GitReleaseMan\Command;

use Mirocode\GitReleaseMan\Command\AbstractCommand as Command;
use Mirocode\GitReleaseMan\Entity\Feature;
use Mirocode\GitReleaseMan\Entity\Release;
use Symfony\Component\Console\Input\InputArgument;
use Mirocode\GitReleaseMan\ExitException as ExitException;

class BuildCommand extends Command
{
    protected $allowedActions = array(
        'init'                     => 'initAction',
        'release-candidate'        => 'releaseCandidateAction',
        'release-stable'           => 'releaseStableAction',
        'features-list'            => 'featuresListAction',
        'latest-release'           => 'latestReleaseAction',
        'latest-release-candidate' => 'latestReleaseCandidateAction',
    );

    protected function configure()
    {
        parent::configure();
        $this->setName('git-release:build')
             ->addArgument('action', InputArgument::REQUIRED, 'Action')
             ->setDescription('Init git release man')
             ->setHelp('Init git release man');
    }

    public function initAction()
    {
        $confirmationMassage = ($this->getConfiguration()->isConfigurationExists())
            ? "Configuration already exists in current repository, do you want to overwrite it?"
            : "Do you want to init git-release-man configuration in current repository?";
        $this->confirmOrExit($confirmationMassage);

        $gitAdapter = ($this->getConfiguration()->getGitAdapter())
            ? $this->getConfiguration()->getGitAdapter()
            : $this->askAndChooseValueOrExit(
                'What is your gitAdapter (github|bitbucket|gitlab)?', array('github', 'bitbucket', 'gitlab'));

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

    public function releaseCandidateAction()
    {
        $this->confirmOrExit('Do you want to build Release Candidate for testing?');
        $features = $this->getGitAdapter()->getFeaturesByLabel($this->getConfiguration()->getLabelForTest());

        if (empty($features)) {
            $this->getStyleHelper()->error('There is no features ready for build.');
            throw new ExitException(ExitException::EXIT_MESSAGE . PHP_EOL);
        }

        foreach ($features as $feature) {
            if (!$feature->getMergeRequest()->getIsMergeable()) {
                throw new ExitException("Feature's '{$feature->getName()}' Merge Request " .
                    "#{$feature->getMergeRequest()->getNumber()} {$feature->getMergeRequest()->getName()} " .
                    "can not be merged. Please, fix it before this action.");
            }
        }

        $releaseCandidate = $this->getGitAdapter()->buildReleaseCandidate($features);

        $this->getStyleHelper()
             ->success("New Release Candidate \"{$releaseCandidate->getVersion()}\" is ready for testing");

    }

    public function releaseStableAction()
    {
        $this->confirmOrExit('Do you want to build Release for production?');
        $features = $this->getGitAdapter()->getFeaturesByLabel($this->getConfiguration()->getLabelForRelease());

        if (empty($features)) {
            $this->getStyleHelper()->error('There is no features ready for build.');
            throw new ExitException(ExitException::EXIT_MESSAGE . PHP_EOL);
        }

        foreach ($features as $feature) {
            if (!$feature->getMergeRequest()->getIsMergeable()) {
                throw new ExitException("Feature's '{$feature->getName()}' Merge Request " .
                    "#{$feature->getMergeRequest()->getNumber()} {$feature->getMergeRequest()->getName()} " .
                    "can not be merged. Please, fix it before this action.");
            }
        }

        $release = $this->getGitAdapter()->buildReleaseStable($features);

        $this->getStyleHelper()
             ->success("New Release \"{$release->getVersion()}\" is ready for production");
    }

    /**
     * List available features
     */
    public function featuresListAction()
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

    public function latestReleaseAction()
    {
        $latestReleaseTag = $this->getGitAdapter()->getLatestReleaseTag();
        $this->getStyleHelper()->write($latestReleaseTag);
    }

    public function latestReleaseCandidateAcition()
    {
        $latestTestReleaseTag = $this->getGitAdapter()->getLatestTestReleaseTag();
        $this->getStyleHelper()->write($latestTestReleaseTag);
    }
}
