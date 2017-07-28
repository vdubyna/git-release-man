<?php

namespace Mirocode\GitReleaseMan\Command;

use Mirocode\GitReleaseMan\Entity\Feature;
use Mirocode\GitReleaseMan\Entity\FeatureInterface;
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
        'open'    => 'open',
        'test'    => 'test',
        'info'    => 'info',
        'reopen'  => 'reopen',
        'close'   => 'close',
        'release' => 'release',
        'list'    => 'featuresList',
    );

    /**
     * @var FeatureInterface
     */
    protected $feature;

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Setup Feature Entity
        $this->feature = new Feature($input->getOption('name'));

        parent::execute($input, $output);
    }

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
    }

    /**
     * TODO implemented Feature information
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
    public function open()
    {
        $this->getStyleHelper()->title('Start new feature');

        $feature = $this->getFeature();
        if ($this->isFeature($feature->getName())) {
            throw new ExitException("The name of the feature \"{$feature->getName()}\" is not valid. " .
                "It should start with prefix \"feature-\"");
        }

        $this->getGitAdapter()->createRemoteBranch($feature->getName());

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
        $this->getStyleHelper()->warning("Delete remote branch automatically close Merge Request");
        $this->confirmOrExit("Do you want to continue this operation:");

        $this->getGitAdapter()->removeRemoteBranch($feature->getName());

        $this->getStyleHelper()->success("Feature \"{$feature->getName()}\" removed from remote repository.");
    }

    /**
     * Removes Pull request from the GitService
     */
    public function reopen()
    {
        $feature = $this->getFeature();

        $this->getStyleHelper()->title("Reopen feature \"{$feature->getName()}\".");
        $this->getStyleHelper()->warning("This action will remove \"release\" and \"test\" labels");
        $this->confirmOrExit("Do you want to continue this operation:");

        $this->getGitAdapter()->closeMergeRequest($feature);

        $this->getStyleHelper()->success("Labels removed from remote repository.");
        $this->getStyleHelper()->note("Feature is ready to continue development, " .
            "changes will not be included into test or release builds");
    }

    /**
     * List available features
     */
    public function featuresList()
    {
        $features = $this->getGitAdapter()->getFeaturesList();
        $headers  = array('Feature Name', 'Pull Request', 'Compare with master');

        $rows = array_map(function ($feature) {
            $pullRequest        = $this->getGitAdapter()->getMergeRequestByFeature($feature);
            $pullRequestMessage = '';

            if (!empty($pullRequest)) {
                $pullRequestMessage = "PR: #{$pullRequest['number']} - {$pullRequest['title']}\n" .
                    "{$pullRequest['html_url']}";
            }

            $compareInfo    = $this->getGitAdapter()->compareFeatureWithMaster($feature);
            $compareMessage = "Status: {$compareInfo['status']}\n" .
                "Behind: {$compareInfo['behind_by']} commits\n" .
                "Ahead: {$compareInfo['ahead_by']} commits\n" .
                "Commits: {$compareInfo['commits']}\n" .
                "Files: {$compareInfo['files']}\n ";

            return array(
                $feature,
                $pullRequestMessage,
                $compareMessage,
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
        $this->confirmOrExit("Do you want to publish feature \"{$feature->getName()}\" for testing:");

        $pullRequest = $this->getGitAdapter()->getMergeRequestByFeature($feature->getName());

        if (empty($pullRequest)) {
            $pullRequestNumber = $this->getGitAdapter()->openMergeRequest($feature->getName());
            $this->getStyleHelper()->success("Pull request \"{$pullRequestNumber}\" created ");
        } else {
            $this->getStyleHelper()
                 ->note("Pull request \"{$pullRequest['number']} - {$pullRequest['title']}\" \n" .
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
        $featureName = $this->getFeatureName();

        $this->getStyleHelper()->title("Mark feature \"{$featureName}\" ready for release");
        $this->confirmOrExit("Do you want to mark feature \"{$featureName}\" to be released:");

        $releaseLabel = $this->getConfiguration()->getPRLabelForRelease();
        $pullRequest  = $this->getGitAdapter()->getMergeRequestByFeature($featureName);

        if (empty($pullRequest)) {
            $this->getStyleHelper()->error("Pull request does not exist for \"{$featureName}\". " .
                "You need to test feature before release.");
        } else {
            $pullRequestNumber = $pullRequest['number'];
            $this->getGitAdapter()->addLabelToPullRequest($pullRequestNumber, $releaseLabel);
            $this->getStyleHelper()->success("Pull request \"{$pullRequestNumber}\"" .
                "marked with label \"{$releaseLabel}\" to be released.");
            $this->getStyleHelper()->success("To move forward execute release command: git:flow release");
        }
    }

    protected function isFeature($featureName)
    {
        return (strpos($featureName, 'feature') === 0);
    }

    /**
     * @return FeatureInterface
     */
    protected function getFeature()
    {
        return $this->feature;
    }
}
