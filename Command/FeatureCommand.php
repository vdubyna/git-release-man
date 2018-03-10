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
        'reset'             => 'resetAction',
        'close'             => 'closeAction',
        'release-candidate' => 'releaseCandidateAction',
        'release-stable'    => 'releaseStableAction',
        'info'              => 'infoAction',
    );

    /**
     * @var Feature
     */
    protected $feature;

    protected $featureName;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('git-release:feature')
             ->addArgument('action', InputArgument::REQUIRED,
                 'Action [' . implode(', ', array_keys($this->allowedActions)) . ']')
             ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Feature Name')
             ->setDescription('Feature git workflow tool')
             ->setHelp('Feature actions: ' . implode(', ', array_keys($this->allowedActions)));
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
        if (!$input->hasOption('name')) {
            throw new ExitException("Option name is not set. It is required and should start with prefix feature-");
        } else {
            $this->featureName = $input->getOption('name');
        }
        parent::execute($input, $output);
    }

    /**
     * @throws ExitException
     */
    public function infoAction()
    {
        $feature = $this->getFeature();

        $this->getStyleHelper()->title('Feature Info');
        $this->getStyleHelper()
             ->success("Feature \"{$feature->getName()}\" STATUS: {$feature->getStatus()}");
    }

    /**
     * We always start new features from Master branch
     * Master branch can be configured
     * @throws ExitException
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
            $this->getStyleHelper()
                 ->success(
                     "Feature {$feature->getName()} already exists on {$this->getConfiguration()->getGitAdapter()}. " .
                     "Status is {$feature->getStatus()}"
                 );
        } else {
            $this->getStyleHelper()
                 ->warning(
                     "Feature {$feature->getName()} status {$feature->getStatus()} is invalid for this operation."
                 );
        }
    }

    /**
     * Removes feature branch from GitService
     * @throws ExitException
     */
    public function closeAction()
    {
        $feature = $this->getFeature();

        $this->getStyleHelper()->title("Close feature \"{$feature->getName()}\".");
        $this->getStyleHelper()->warning("Delete branch will automatically close Merge Request if it exists/open");
        $this->confirmOrExit("Do you want to continue this operation:");

        $feature = $this->getGitAdapter()->closeFeature($feature);

        if ($feature->getStatus() === Feature::STATUS_CLOSED) {
            $this->getStyleHelper()->success("Feature \"{$feature->getName()}\" closed and branch is deleted.");
        }
    }

    /**
     * Removes feature branch from GitService
     * @throws ExitException
     */
    public function resetAction()
    {
        $feature = $this->getFeature();

        $this->getStyleHelper()->title("Reset feature \"{$feature->getName()}\".");
        $this->confirmOrExit("Do you want to continue this operation:");

        $this->getGitAdapter()->markFeatureAsNew($feature);
        $this->getStyleHelper()->success("Feature \"{$feature->getName()}\" was reset.");
    }

    /**
     * Open Pull Request to make feature available for QA testing
     * @throws ExitException
     */
    public function releaseCandidateAction()
    {
        $feature = $this->getFeature();

        $this->getStyleHelper()->title("Mark feature \"{$feature->getName()}\" ready for release-candidate build");
        $this->confirmOrExit("Do you want to mark feature \"{$feature->getName()}\" release-candidate:");

        if ($feature->getStatus() === Feature::STATUS_NEW) {
            $this->getStyleHelper()
                 ->warning("Feature {$feature->getName()} should be started before go to release-candidate status.");
        } elseif ($feature->getStatus() === Feature::STATUS_STARTED) {
            $this->getGitAdapter()
                 ->markFeatureReadyForReleaseCandidate($feature);
            $this->getStyleHelper()
                 ->success("Feature marked ready for release-candidate build");
            $this->getStyleHelper()->success(
                "To move forward execute release-candidate build command: git-release:build release-candidate");
        } elseif ($feature->getStatus() === Feature::STATUS_RELEASE_CANDIDATE) {
            $this->getStyleHelper()
                 ->note("Feature {$feature->getName()} already marked ready for release-candidate build.");
            $this->getStyleHelper()->success(
                "To move forward execute release-candidate build command: git-release:build release-candidate");
        } else {
            $this->getStyleHelper()
                 ->warning("Feature status: {$feature->getStatus()} not valid to go to release-candidate build.");
        }
    }

    /**
     * Mark Merge Request ready to go to production
     * @throws ExitException
     */
    public function releaseStableAction()
    {
        $feature = $this->getFeature();

        $this->getStyleHelper()->title("Mark feature \"{$feature->getName()}\" ready for release-stable build");
        $this->confirmOrExit("Do you want to mark feature \"{$feature->getName()}\" release-stable:");

        if ($feature->getStatus() === Feature::STATUS_NEW || $feature->getStatus() === Feature::STATUS_STARTED) {
            $this->getStyleHelper()
                 ->warning(
                     "Feature \"{$feature->getName()}\" should be marked \"release-candidate\" " .
                     "before go to \"release-stable\" status."
                 );
        } elseif ($feature->getStatus() === Feature::STATUS_RELEASE_CANDIDATE) {
            $this->getGitAdapter()
                 ->markFeatureReadyForReleaseStable($feature);
            $this->getStyleHelper()
                 ->success("Feature {$feature->getName()} marked ready for release-stable build.");
            $this->getStyleHelper()
                 ->success("To move forward execute release-stable build command: git-release:build release-stable");
        } elseif ($feature->getStatus() === Feature::STATUS_RELEASE_STABLE) {
            $this->getStyleHelper()
                 ->note("Feature {$feature->getName()} already marked ready for release-stable build.");
            $this->getStyleHelper()->success(
                "To move forward execute release-stable build command: git-release:build release-stable");
        } else {
            $this->getStyleHelper()
                 ->warning("Feature status: {$feature->getStatus()} not valid to go to release-stable build.");
        }
    }

    /**
     * @return Feature
     * @throws ExitException
     */
    public function getFeature()
    {
        if (empty($this->feature)) {
            $featureName = $this->featureName;
            if (0 === strpos($featureName, 'feature-')) {
                $this->feature = $this->getGitAdapter()->buildFeature($this->featureName);
            } else {
                throw new ExitException(
                    "Feature name {$featureName} is not valid. It should start with feature- prefix"
                );
            }
        }

        return $this->feature;
    }
}
