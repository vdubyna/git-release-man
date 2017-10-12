<?php

namespace Mirocode\GitReleaseMan\Command;

use Github\Api\Issue\Labels;
use Mirocode\GitReleaseMan\Command\AbstractCommand as Command;
use Mirocode\GitReleaseMan\Entity\Feature;
use Mirocode\GitReleaseMan\Entity\Release;
use Symfony\Component\Console\Input\InputArgument;
use Mirocode\GitReleaseMan\ExitException as ExitException;

class BuildCommand extends Command
{
    protected $allowedActions = [
        'init'                     => 'initAction',
        'release-candidate'        => 'releaseCandidateAction',
        'release-stable'           => 'releaseStableAction',
        'latest-release-stable'    => 'latestReleaseStableAction',
        'latest-release-candidate' => 'latestReleaseCandidateAction',
        'features-list'            => 'featuresListAction',
    ];

    protected function configure()
    {
        parent::configure();
        $this->setName('git-release:build')
             ->addArgument('action', InputArgument::REQUIRED, 'Action')
             ->setDescription('Init git release man')
             ->setHelp('Build actions: ' . implode(', ', array_keys($this->allowedActions)));
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
        $features = $this->getGitAdapter()->getFeaturesByLabel($this->getConfiguration()->getLabelForReleaseCandidate());

        if (empty($features)) {
            $this->getStyleHelper()->error('There is no features ready for build.');
            throw new ExitException(ExitException::EXIT_MESSAGE . PHP_EOL);
        }

        $releaseCandidateVersion = $this->getGitAdapter()->getReleaseCandidateVersion();
        $releaseCandidate = new Release(
            $releaseCandidateVersion,
            $releaseCandidateVersion->__toString(),
            Release::TYPE_RELEASE_CANDIDATE
        );
        $releaseCandidate = $this->getGitAdapter()->startReleaseCandidate($releaseCandidate);

        foreach ($features as $feature) {
            if (!$this->getGitAdapter()->isFeatureReadyForRelease($feature, $releaseCandidate)) {
                throw new ExitException(
                    "Feature '{$feature->getName()}' can not be merged. Please, fix it before this action.");
            }
        }

        foreach ($features as $feature) {
            $this->getGitAdapter()->pushFeatureIntoReleaseCandidate($releaseCandidate, $feature);
            $this->getStyleHelper()->success("Feature {$feature->getMergeRequestNumber()} " .
                "- {$feature->getName()} pushed into release {$releaseCandidate->getVersion()}");
        }

        $releaseCandidate->setMetadata(date('Y-m-d_h-i-s'));
        $this->getGitAdapter()->createReleaseTag($releaseCandidate);

        $this->getStyleHelper()
             ->success("New Release Candidate \"{$releaseCandidate->getVersion()}\" is ready for testing");

    }

    public function releaseStableAction()
    {
        $this->confirmOrExit('Do you want to build Release for production?');
        $features = $this->getGitAdapter()->getFeaturesByLabel($this->getConfiguration()->getLabelForReleaseStable());

        if (empty($features)) {
            $this->getStyleHelper()->error('There is no features ready for build.');
            throw new ExitException(ExitException::EXIT_MESSAGE . PHP_EOL);
        }
        $releaseStableVersion = $this->getGitAdapter()->getReleaseStableVersion();
        $releaseStable = new Release(
            $releaseStableVersion,
            $this->getConfiguration()->getMasterBranch(),
            Release::TYPE_RELEASE_STABLE
        );

        foreach ($features as $feature) {
            if (!$this->getGitAdapter()->isFeatureReadyForRelease($feature, $releaseStable)) {
                throw new ExitException("Feature's '{$feature->getName()}' Merge Request " .
                    "#{$feature->getMergeRequest()->getNumber()} {$feature->getMergeRequest()->getName()} " .
                    "can not be merged. Please, fix it before this action.");
            }
        }

        foreach ($features as $feature) {
            $this->getStyleHelper()->note("Feature {$feature->getMergeRequestNumber()} " .
                "- {$feature->getName()} try merge into release {$releaseStable->getVersion()}");
            $this->getGitAdapter()->pushFeatureIntoReleaseStable($releaseStable, $feature);
            $this->getStyleHelper()->success("Feature {$feature->getMergeRequestNumber()} " .
                "- {$feature->getName()} pushed into release {$releaseStable->getVersion()}");
        }

        $this->getGitAdapter()->createReleaseTag($releaseStable);
        $this->getGitAdapter()->cleanupRelease($releaseStable);

        $this->getStyleHelper()
             ->success("New Release \"{$releaseStable->getVersion()}\" is ready for production");
    }

    /**
     * List available features
     */
    public function featuresListAction()
    {
        $features = $this->getGitAdapter()->getFeaturesList();
        $headers  = array('Feature Name', 'Labels', 'Merge Request');

        $rows = array_map(function (Feature $feature) {
            if (empty($feature->getMergeRequestNumber())) {
                $mergeRequestMessage = "There is no open MergeRequest";
            } else {
                $mergeRequest = $feature->getMergeRequest();
                $mergeRequestMessage = "Merge Request: #{$mergeRequest->getNumber()} - {$mergeRequest->getName()}\n" .
                    "{$mergeRequest->getUrl()}";
            }

            return [
                $feature->getName(),
                implode(', ', $feature->getLabels()),
                $mergeRequestMessage,
            ];
        }, $features);

        $this->getStyleHelper()->section("Features list");
        $this->getStyleHelper()->table($headers, $rows);
    }

    public function latestReleaseStableAction()
    {
        $latestReleaseTag = $this->getGitAdapter()->getLatestReleaseStableTag();
        $this->getStyleHelper()->write($latestReleaseTag);
    }

    public function latestReleaseCandidateAcition()
    {
        $latestTestReleaseTag = $this->getGitAdapter()->getLatestReleaseCandidateTag();
        $this->getStyleHelper()->write($latestTestReleaseTag);
    }
}
