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
        'start'   => 'start',
        'close'   => 'close',
        'test'    => 'test',
        'release' => 'release',
        'info'    => 'info',
        'list'    => 'featuresList',
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
             ->addArgument('name', InputArgument::REQUIRED, 'Feature Name')
             ->addArgument('action', InputArgument::REQUIRED, 'Action [start, close, test, release, info, list]')
             ->setDescription('Feature git workflow tool')
             ->setHelp('Feature actions: list, open, close, reopen, test, release, info');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->feature = $this->getGitAdapter()
                              ->buildFeature($input->getArgument('name'));
        parent::execute($input, $output);
    }

    public function info()
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
    public function start()
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
        } elseif ($feature->getStatus() === Feature::STATUS_TEST
            || $feature->getStatus() === Feature::STATUS_RELEASE
        ) {
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
    public function close()
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
    public function test()
    {
        $feature = $this->getFeature();

        $this->getStyleHelper()->title("Mark feature \"{$feature->getName()}\" ready for testing");
        $this->confirmOrExit("Do you want to publish feature \"{$feature->getName()}\" for testing:");

        if ($feature->getStatus() === Feature::STATUS_NEW) {
            $this->getStyleHelper()
                 ->warning("Feature {$feature->getName()} should be started before go to test status.");
        } elseif ($feature->getStatus() === Feature::STATUS_STARTED) {
            if (!$feature->getMergeRequestNumber()) {
                $mergeRequest = $this->getGitAdapter()
                                     ->openMergeRequestByFeature($feature);
                $feature->setMergeRequestNumber($mergeRequest->getNumber());
            }

            $this->getGitAdapter()
                 ->markFeatureReadyForTest($feature);
            $this->getStyleHelper()
                 ->success("Merge request \"{$feature->getMergeRequestNumber()}\" created ");
            $this->getStyleHelper()->success("To move forward execute test command: git-release:build test");
        } elseif ($feature->getStatus() === Feature::STATUS_TEST) {
            $this->getStyleHelper()
                 ->note("Feature {$feature->getName()} already marked ready for test. " .
                     "See merge request {$feature->getMergeRequestNumber()}.");
            $this->getStyleHelper()->success("To move forward execute test command: git-release:build test");
        } else {
            $this->getStyleHelper()
                 ->warning("Feature status: {$feature->getStatus()} not valid to go to test.");
        }
    }

    /**
     * Mark Merge Request ready to go to production
     */
    public function release()
    {
        $feature = $this->getFeature();

        $this->getStyleHelper()->title("Mark feature \"{$feature->getName()}\" ready for testing");
        $this->confirmOrExit("Do you want to publish feature \"{$feature->getName()}\" for testing:");

        if ($feature->getStatus() === Feature::STATUS_NEW || $feature->getStatus() === Feature::STATUS_STARTED) {
            $this->getStyleHelper()
                 ->warning(
                     "Feature {$feature->getName()} should be marked ready for test before go to release status."
                 );
        } elseif ($feature->getStatus() === Feature::STATUS_TEST) {
            $this->getGitAdapter()
                 ->markFeatureReadyForRelease($feature);
            $this->getStyleHelper()
                 ->success("Feature {$feature->getName()} marked ready for release.");
            $this->getStyleHelper()
                 ->success("To move forward execute test command: git-release:build release");
        } elseif ($feature->getStatus() === Feature::STATUS_RELEASE) {
            $this->getStyleHelper()
                 ->note("Feature {$feature->getName()} already marked ready for release. " .
                     "See merge request {$feature->getMergeRequestNumber()}.");
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
