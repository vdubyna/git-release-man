<?php

namespace Mirocode\GitReleaseMan\Command;

use Mirocode\GitReleaseMan\Entity\Feature;
use Mirocode\GitReleaseMan\Entity\MergeRequest;
use Symfony\Component\Console\Input\InputInterface;
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
             ->addArgument('action', InputArgument::REQUIRED, 'Action')
             ->addOption('name', null, InputArgument::REQUIRED, 'Feature Name')
             ->setDescription('Feature git workflow tool')
             ->setHelp('Feature actions: list, open, close, reopen, test, release, info');
        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->feature = $this->getGitAdapter()->buildFeature($input->getOption('name'));

        parent::execute($input, $output);
    }

    /**
     * TODO implement Feature information
     */
    public function info()
    {
        $this->getStyleHelper()->title('Feature Info');
        $feature = $this->getFeature();
        $this->getStyleHelper()
             ->success("Feature {$feature->getName()}");
    }

    /**
     * We always start new features from Master branch
     * Master branch can be configured
     */
    public function start()
    {
        $feature = $this->getFeature();

        $this->getStyleHelper()->title("Start new feature {$feature->getName()}");
        if (!$this->isNoQuestionsEnabled()) {
            $this->confirmOrExit("Do you want to continue this operation:");
        }

        // TODO verify the feature already exists
        //$this->getStyleHelper()->warning("This action will remove \"release\" and \"test\" labels");
        //$this->confirmOrExit("Do you want to continue this operation:");
        //
        //$this->getGitAdapter()->closeMergeRequestByFeature($feature);
        //
        //$this->getStyleHelper()->success("Labels removed from remote repository.");
        //$this->getStyleHelper()->note("Feature is ready to continue development, " .
        //    "changes will not be included into test or release builds");


        $feature = $this->getGitAdapter()->startFeature($feature);
        // TODO verify exceptions and feature status
        $this->getStyleHelper()
             ->success("Feature {$feature->getName()} successfully created on remote repository.");
    }

    /**
     * Removes feature branch from GitService
     */
    public function close()
    {
        $feature = $this->getFeature();

        $this->getStyleHelper()->title("Close feature \"{$feature->getName()}\".");
        if (!$this->isNoQuestionsEnabled()) {
            $this->getStyleHelper()->warning("Delete remote branch automatically close Merge Request");
            $this->confirmOrExit("Do you want to continue this operation:");
        }
        $feature = $this->getGitAdapter()->closeFeature($feature);
        // TODO verify exceptions and feature status
        $this->getStyleHelper()->success("Feature \"{$feature->getName()}\" removed from remote repository.");
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

    /**
     * Open Pull Request to make feature available for QA testing
     */
    public function test()
    {
        $feature = $this->getFeature();

        $this->getStyleHelper()->title("Mark feature \"{$feature->getName()}\" ready for testing");
        if (!$this->isNoQuestionsEnabled()) {
            $this->confirmOrExit("Do you want to publish feature \"{$feature->getName()}\" for testing:");
        }

        $mergeRequest = $this->getGitAdapter()->getMergeRequestByFeature($feature);

        if (empty($mergeRequest->getNumber())) {
            $mergeRequest = $this->getGitAdapter()->openMergeRequestByFeature($feature);
            $this->getGitAdapter()->markMergeRequestReadyForTest($mergeRequest);
            // TODO verify exceptions and status
            $this->getStyleHelper()->success("Pull request \"{$mergeRequest->getNumber()}\" created ");
        } else {
            $this->getGitAdapter()->markMergeRequestReadyForTest($mergeRequest);
            // TODO verify exceptions and status
            $this->getStyleHelper()
                 ->note("Pull request \"{$mergeRequest->getName()} - {$mergeRequest->getName()}\" \n" .
                     "already exists for feature: \"{$feature->getName()}\"");
        }

        $this->getStyleHelper()->success("To move forward execute test command: git-release:build test");
    }

    /**
     * Mark Feature ready for release
     **
     * @throws ExitException
     */
    public function release()
    {
        $feature = $this->getFeature();

        $this->getStyleHelper()->title("Mark feature \"{$feature->getName()}\" ready for release");
        if (!$this->isNoQuestionsEnabled()) {
            $this->confirmOrExit("Do you want to mark feature \"{$feature->getName()}\" to be released:");
        }

        $mergeRequest = $this->getGitAdapter()->getMergeRequestByFeature($feature);

        if (empty($mergeRequest)) {
            $this->getStyleHelper()->error("Merge Request does not exist for \"{$feature->getName()}\" " .
                "or it was not marked Ready for Test. " .
                "You need to test feature before release.");
        } else {
            $mergeRequest = $this->getGitAdapter()->markMergeRequestReadyForRelease($mergeRequest);
            // TODO verify exceptions and status
            $this->getStyleHelper()->success("Pull request \"{$mergeRequest->getNumber()}\" marked to be released.");
            $this->getStyleHelper()->success("To move forward execute release command: git-release:build release");
        }
    }

    /**
     * @return Feature
     */
    protected function getFeature()
    {
        return $this->feature;
    }
}
