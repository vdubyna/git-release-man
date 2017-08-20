<?php

namespace Mirocode\GitReleaseMan\Command;

use Mirocode\GitReleaseMan\Entity\Feature;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Mirocode\GitReleaseMan\Command\AbstractCommand as Command;
use Symfony\Component\Console\Input\InputArgument;
use Mirocode\GitReleaseMan\ExitException as ExitException;

class FeatureCommand extends Command
{
    /**
     * @inheritdoc
     */
    protected $allowedActions = array(
        'start'             => 'startAction',
        'close'             => 'closeAction',
        'release-candidate' => 'releaseCandidateAction',
        'release-stable'    => 'releaseStableAction',
        'info'              => 'infoAction',
    );

    /**
     * @var Feature
     */
    protected $feature;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('git-release:feature')
             ->addArgument('action', InputArgument::REQUIRED,
                 'Action [' . implode(', ', array_keys($this->allowedActions)) . ']')
             ->addOption('name', 'n', InputOption::VALUE_REQUIRED, 'Feature Name')
             ->setDescription('Feature git workflow tool')
             ->setHelp('Feature actions: ' . implode(', ', array_keys($this->allowedActions)));
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->feature = $this->getGitAdapter()
                              ->buildFeature($input->getOption('name'));
        parent::execute($input, $output);
    }

    public function infoAction()
    {
        $feature = $this->getFeature();

        $this->getStyleHelper()->title('Feature Info');
        $this->getStyleHelper()
             ->note("Feature {$feature->getName()} STATUS: {$feature->getStatus()}");
    }

    /**
     * We always start new features from Master branch
     * Master branch can be configured
     */
    public function startAction()
    {
        $feature = $this->getFeature();

        $this->getStyleHelper()->title("Start feature {$feature->getName()}");
        $this->confirmOrExit("Do you want to continue this operation:");

        if ($feature->getStatus() === Feature::STATUS_NEW) {
            $feature = $this->getGitAdapter()->startFeature($feature);
            if ($feature->getStatus() === Feature::STATUS_STARTED) {
                $this->getStyleHelper()
                     ->success("Feature {$feature->getName()} successfully created on remote repository.");
            }
        } elseif ($feature->getStatus() === Feature::STATUS_STARTED) {
            $this->getStyleHelper()
                 ->success(
                     "Feature {$feature->getName()} already exists on {$this->getConfiguration()->getGitAdapter()}."
                 );
        } elseif ($feature->getStatus() === Feature::STATUS_RELEASE_CANDIDATE
            || $feature->getStatus() === Feature::STATUS_RELEASE_STABLE
        ) {
            $this->confirmOrExit("Do you want to re-start this feature:");
            $this->getGitAdapter()->markFeatureAsNew($feature);
        } else {
            $this->getStyleHelper()
                 ->warning(
                     "Feature {$feature->getName()} status {$feature->getStatus()} is invalid for this operation."
                 );
        }
    }

    /**
     * Removes feature branch from GitService
     */
    public function closeAction()
    {
        $feature = $this->getFeature();

        $this->getStyleHelper()->title("Close feature \"{$feature->getName()}\".");
        $this->getStyleHelper()->warning("Delete remote branch automatically close Merge Request");
        $this->confirmOrExit("Do you want to continue this operation:");

        $feature = $this->getGitAdapter()->closeFeature($feature);

        if ($feature->getStatus() === Feature::STATUS_CLOSE) {
            $this->getStyleHelper()->success("Feature \"{$feature->getName()}\" removed from remote repository.");
        }
    }

    /**
     * Open Pull Request to make feature available for QA testing
     */
    public function releaseCandidateAction()
    {
        $feature = $this->getFeature();

        $this->getStyleHelper()->title("Mark feature \"{$feature->getName()}\" ready for testing");
        $this->confirmOrExit("Do you want to publish feature \"{$feature->getName()}\" for testing:");

        if ($feature->getStatus() === Feature::STATUS_NEW) {
            $this->getStyleHelper()
                 ->warning("Feature {$feature->getName()} should be started before go to test status.");
        } elseif ($feature->getStatus() === Feature::STATUS_STARTED) {
            $this->getGitAdapter()
                 ->markFeatureReadyForReleaseCandidate($feature);
            $this->getStyleHelper()
                 ->success("Feature marked for release candidate");
            $this->getStyleHelper()->success("To move forward execute test command: git-release:build test");
        } elseif ($feature->getStatus() === Feature::STATUS_RELEASE_CANDIDATE) {
            $this->getStyleHelper()
                 ->note("Feature {$feature->getName()} already marked ready for test.");
            $this->getStyleHelper()->success("To move forward execute test command: git-release:build test");
        } else {
            $this->getStyleHelper()
                 ->warning("Feature status: {$feature->getStatus()} not valid to go to test.");
        }
    }

    /**
     * Mark Merge Request ready to go to production
     */
    public function releaseStableAction()
    {
        $feature = $this->getFeature();

        $this->getStyleHelper()->title("Mark feature \"{$feature->getName()}\" ready for testing");
        $this->confirmOrExit("Do you want to publish feature \"{$feature->getName()}\" for testing:");

        if ($feature->getStatus() === Feature::STATUS_NEW || $feature->getStatus() === Feature::STATUS_STARTED) {
            $this->getStyleHelper()
                 ->warning(
                     "Feature {$feature->getName()} should be marked ready for test before go to release status."
                 );
        } elseif ($feature->getStatus() === Feature::STATUS_RELEASE_CANDIDATE) {
            $this->getGitAdapter()
                 ->markFeatureReadyForReleaseStable($feature);
            $this->getStyleHelper()
                 ->success("Feature {$feature->getName()} marked ready for release.");
            $this->getStyleHelper()
                 ->success("To move forward execute test command: git-release:build release");
        } elseif ($feature->getStatus() === Feature::STATUS_RELEASE_STABLE) {
            $this->getStyleHelper()
                 ->note("Feature {$feature->getName()} already marked ready for release.");
            $this->getStyleHelper()->success("To move forward execute test command: git-release:build release");
        } else {
            $this->getStyleHelper()
                 ->warning("Feature status: {$feature->getStatus()} not valid to go to release.");
        }
    }

    /**
     * @return Feature
     */
    public function getFeature()
    {
        return $this->feature;
    }
}
