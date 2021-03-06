<?php

namespace Mirocode\GitReleaseMan\Command;

use Mirocode\GitReleaseMan\Command\AbstractCommand as Command;
use Mirocode\GitReleaseMan\Entity\Feature;
use Mirocode\GitReleaseMan\Entity\Release;
use Mirocode\GitReleaseMan\ExitException;
use Mirocode\GitReleaseMan\MergeException;
use Mirocode\GitReleaseMan\Version;
use Symfony\Component\Console\Input\InputArgument;

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
             ->setAliases(['g:b'])
             ->addArgument('action', InputArgument::REQUIRED, 'Action')
             ->setDescription('Manage release based on semver workflow.')
             ->setHelp('Build actions: ' . implode(', ', array_keys($this->allowedActions)));
    }

    /**
     * @throws ExitException
     */
    public function initAction()
    {
        $confirmationMassage = ($this->getConfiguration()->isConfigurationExists())
            ? "Configuration already exists in current repository, do you want to overwrite it?"
            : "Do you want to init git-release-man configuration in current repository?";
        $this->confirmOrExit($confirmationMassage);

        $gitAdapter = $this->askAndChooseValueOrExit(
            'What is your gitAdapter (github|bitbucket|gitlab|gitlocal|gitremote)?',
            ['github', 'bitbucket', 'gitlab', 'gitlocal', 'gitremote'],
            $this->getConfiguration()->getGitAdapter()
        );

        if (in_array($gitAdapter, ['gitlocal', 'gitremote'])) {
            $this->getConfiguration()->initConfiguration($gitAdapter);
        } else {
            $username       = $this->askAndGetValueOrExit('What is your username?',
                $this->getConfiguration()->getUsername());
            $token          = $this->askAndGetValueOrExit('What is your token?',
                $this->getConfiguration()->getToken());
            $repositoryName = $this->askAndGetValueOrExit('What is your repository name?',
                $this->getConfiguration()->getRepository());
            $this->getConfiguration()->initConfiguration($gitAdapter, $username, $token, $repositoryName);
        }
    }

    /**
     * @throws ExitException
     */
    public function releaseCandidateAction()
    {
        if (!$this->forceExecute) {
            $this->confirmOrExit('Do you want to build Release Candidate for testing?');
        }
        $features = $this->getGitAdapter()->getFeaturesByLabel($this->getConfiguration()
                                                                    ->getLabelForReleaseCandidate());

        if (empty($features)) {
            $this->getStyleHelper()->error('There is no features ready for build.');
            throw new ExitException(ExitException::EXIT_MESSAGE . PHP_EOL);
        }

        $stableVersion = $this->getGitAdapter()->getReleaseStableVersion();
        if (!$this->forceExecute) {
            $versionType = $this->askAndGetValueOrExit(
                "Current stable version {$stableVersion->__toString()}. What release type[MINOR,MAJOR,PATCH]:",
                Version::TYPE_PATCH
            );
        } else {
            $versionType = Version::TYPE_PATCH;
        }
        $releaseCandidateVersion = $this->getGitAdapter()->getReleaseCandidateVersion($versionType);
        $releaseCandidate = new Release(
            $releaseCandidateVersion,
            $releaseCandidateVersion->__toString(),
            Release::TYPE_RELEASE_CANDIDATE
        );
        $releaseCandidate = $this->getGitAdapter()->startReleaseCandidate($releaseCandidate);

        foreach ($features as $feature) {
            try {
                $this->getGitAdapter()->isFeatureReadyForRelease($feature, $releaseCandidate);
            } catch (MergeException $e) {
                $this->getGitAdapter()->removeReleaseCandidate($releaseCandidate);
                throw new ExitException($e);
            }
        }

        foreach ($features as $feature) {
            $this->getGitAdapter()->pushFeatureIntoReleaseCandidate($releaseCandidate, $feature);
            $featureIdentifier = ($feature->getReleaseRequest())
                ? $feature->getReleaseRequest()->getNumber() : $feature->getName();
            $this->getStyleHelper()->success("Feature {$featureIdentifier} " .
                "- {$feature->getName()} pushed into release {$releaseCandidate->getVersion()}");
        }

        $releaseCandidate->setMetadata(date('Y-m-d_h-i-s'));
        $this->getGitAdapter()->createReleaseTag($releaseCandidate);

        $this->getStyleHelper()
             ->success("New Release Candidate \"{$releaseCandidate->getVersion()}\" is ready for testing");
    }

    /**
     * @throws ExitException
     */
    public function releaseStableAction()
    {
        if (!$this->forceExecute) {
            $this->confirmOrExit('Do you want to build Release for production?');
        }
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
            try {
                $this->getGitAdapter()->isFeatureReadyForRelease($feature, $releaseStable);
            } catch (MergeException $e) {
                throw new ExitException($e);
            }
        }

        foreach ($features as $feature) {
            $featureIdentifier = ($feature->getReleaseRequest())
                ? $feature->getReleaseRequest()->getNumber() : $feature->getName();
            $this->getStyleHelper()->note("Feature {$featureIdentifier} " .
                "- {$feature->getName()} try merge into release {$releaseStable->getVersion()}");
            $this->getGitAdapter()->pushFeatureIntoReleaseStable($releaseStable, $feature);
            $this->getStyleHelper()->success("Feature {$featureIdentifier} " .
                "- {$feature->getName()} pushed into release {$releaseStable->getVersion()}");
        }

        $this->getGitAdapter()->createReleaseTag($releaseStable);
        $this->getGitAdapter()->cleanupRelease($releaseStable);

        $this->getStyleHelper()
             ->success("New Release \"{$releaseStable->getVersion()}\" is ready for production");
    }

    /**
     * List available features
     * @throws ExitException
     */
    public function featuresListAction()
    {
        $features = $this->getGitAdapter()->getFeaturesList();
        $headers  = array('Feature Name', 'Labels', 'Release Request');

        $rows = array_map(function (Feature $feature) {
            if (empty($feature->getReleaseRequest())) {
                $releaseRequestMessage = "There is no open Release Request";
            } else {
                $releaseRequest = $feature->getReleaseRequest();
                $releaseRequestMessage = "Release Request: #{$releaseRequest->getNumber()} " .
                    "- {$releaseRequest->getName()}\n" .
                    "{$releaseRequest->getUrl()}";
            }

            return [
                $feature->getName(),
                implode(', ', $feature->getLabels()),
                $releaseRequestMessage,
            ];
        }, $features);

        $this->getStyleHelper()->success("Features list");
        $this->getStyleHelper()->table($headers, $rows);
    }

    /**
     * @throws ExitException
     */
    public function latestReleaseStableAction()
    {
        $latestReleaseTag = $this->getGitAdapter()->getLatestReleaseStableTag();
        $this->getStyleHelper()->success('Latest stable release tag');
        $this->getStyleHelper()->note($latestReleaseTag);
    }

    /**
     * @throws ExitException
     */
    public function latestReleaseCandidateAction()
    {
        $latestTestReleaseTag = $this->getGitAdapter()->getLatestReleaseCandidateTag();
        $this->getStyleHelper()->success('Latest candidate release tag');
        $this->getStyleHelper()->note($latestTestReleaseTag);
    }
}
