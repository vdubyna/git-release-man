<?php
/**
 * Created by PhpStorm.
 * User: vdubyna
 * Date: 6/17/17
 * Time: 12:07
 */

namespace Mirocode\GitReleaseMan;

use Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Mirocode\GitReleaseMan\AbstractCommand as Command;
use Symfony\Component\Console\Input\InputArgument;
use Mirocode\GitReleaseMan\ExitException as ExitException;

class FeatureCommand extends Command
{
    protected $allowedActions = array(
        'open'    => 'open',
        'test'    => 'test',
        'reopen'  => 'reopen',
        'close'   => 'close',
        'release' => 'release',
        'list'    => 'featuresList',
    );

    protected $featureName;

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $featureName = $input->getOption('name');

        if (!empty($featureName)) {
            $this->featureName = $featureName;
        }

        parent::execute($input, $output);
    }


    protected function configure()
    {
        $this->setName('git-release:feature')
             ->addArgument('action', InputArgument::REQUIRED, 'Action')
             ->addOption('name', null, InputArgument::OPTIONAL, 'Feature Name')
             ->setDescription('Process feature.')
             ->setHelp('Process feature');
    }

    /**
     * We always start new features from master branch!
     *
     * @throws \Mirocode\GitReleaseMan\ExitException
     */
    public function open()
    {
        $this->getStyleHelper()->title('Start new feature');

        $featureName = $this->getFeatureName();

        $featureFullName = "feature-{$featureName}";
        $this->getGitAdapter()->createRemoteBranch($featureName);

        $this->getStyleHelper()
             ->success("Feature {$featureFullName} successfully created on remote repository.");
    }

    /**
     * Remove feature branch from remote repository
     * Close PR if needed
     *
     * @throws \Mirocode\GitReleaseMan\ExitException
     */
    public function close()
    {
        $featureName = $this->getFeatureName();
        $this->getStyleHelper()->title("Close feature \"{$featureName}\".");
        $this->getStyleHelper()->warning("Delete remote branch automatically close the Pull Request");
        $this->confirmOrExit("Do you want to continue this operation:");
        $this->getGitAdapter()->removeRemoteBranch($featureName);
        $this->getStyleHelper()->success("Feature \"{$featureName}\" removed from remote repository.");
    }

    public function reopen()
    {
        $featureName = $this->getFeatureName();
        $this->getStyleHelper()->title("Reopen feature \"{$featureName}\".");
        $this->getStyleHelper()->warning("This action will remove \"release\" and \"test\" labels");
        $this->confirmOrExit("Do you want to continue this operation:");
        $this->getGitAdapter()->removeLabelsFromPullRequest($featureName);
        $this->getStyleHelper()->success("Labels removed from remote repository.");
        $this->getStyleHelper()->note("Feature is ready to continue development, " .
            "changes will not be included into test or release builds");
    }

    public function featuresList()
    {
        $features = $this->getGitAdapter()->getFeaturesList();
        $headers  = array('Feature Name', 'Pull Request', 'Labels', 'Compare with master');

        $rows = array_map(function($feature) {
            $pullRequest        = $this->getGitAdapter()->getPullRequestByFeature($feature);
            $pullRequestMessage = '';
            $labelsMessage      = '';

            if (!empty($pullRequest)) {
                $labels             = $this->getGitAdapter()->getLabelsByPullRequest($pullRequest['number']);
                $labelsMessage      = implode(', ', $labels);
                $pullRequestMessage = "PR: #{$pullRequest['number']} - {$pullRequest['title']}\n" .
                    "{$pullRequest['html_url']}";
            }

            $compareInfo = $this->getGitAdapter()->compareFeatureWithMaster($feature);
            $compareMessage = "Status: {$compareInfo['status']}\n" .
                "Behind: {$compareInfo['behind_by']} commits\n" .
                "Ahead: {$compareInfo['ahead_by']} commits\n" .
                "Commits: {$compareInfo['commits']}\n" .
                "Files: {$compareInfo['files']}\n ";

            return array(
                $feature,
                $pullRequestMessage,
                $labelsMessage,
                $compareMessage
            );
        }, $features);

        $this->getStyleHelper()->section("Features list");
        $this->getStyleHelper()->table($headers, $rows);
    }

    public function test()
    {
        $featureName = $this->getFeatureName();
        $this->getStyleHelper()->title("Mark feature \"{$featureName}\" ready for testing");
        $this->confirmOrExit("Do you want to publish feature \"{$featureName}\" for testing:");

        $testLabel   = $this->getConfiguration()->getPRLabelForTest();
        $pullRequest = $this->getGitAdapter()->getPullRequestByFeature($featureName);

        if (empty($pullRequest)) {
            $pullRequestNumber = $this->getGitAdapter()->openPullRequest($featureName);
            $this->getGitAdapter()->addLabelToPullRequest($pullRequestNumber, $testLabel);
            $this->getStyleHelper()->success("Pull request \"{$pullRequestNumber}\" created " .
                "and marked with label \"{$testLabel}\" for testing.");
        } else {
            $this->getGitAdapter()->addLabelToPullRequest($pullRequest['number'], $testLabel);
            $this->getStyleHelper()
                 ->note("Pull request \"{$pullRequest['number']} - {$pullRequest['title']}\" \n" .
                     "already exists for feature: \"{$featureName}\"");
            $this->getStyleHelper()->success("Marked with \"{$testLabel}\" label for testing");
            $this->getStyleHelper()->success("To move forward execute test command: git:flow test");
        }
    }

    /**
     * Mark Feature ready for release
     **
     * @throws \Mirocode\GitReleaseMan\ExitException
     */
    public function release()
    {
        $featureName = $this->getFeatureName();

        $this->getStyleHelper()->title("Mark feature \"{$featureName}\" ready for release");
        $this->confirmOrExit("Do you want to mark feature \"{$featureName}\" to be released:");

        $releaseLabel = $this->getConfiguration()->getPRLabelForRelease();
        $pullRequest  = $this->getGitAdapter()->getPullRequestByFeature($featureName);

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
        return (strpos($featureName, 'feature') !== false);
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getFeatureName()
    {
        if (empty($this->featureName)) {
            $featureName = $this->getCurrentBranch();
            if (!$this->isFeature($featureName)) {
                throw new ExitException("Feature {$featureName} is not feature");
            }
            $this->setFeatureName($featureName);
        }

        return $this->featureName;
    }

    /**
     * @param mixed $featureName
     *
     * @throws Exception
     */
    public function setFeatureName($featureName)
    {
        $this->featureName = $featureName;
    }

    /**
     * @return string
     */
    public function getCurrentBranch()
    {
        return trim($this->executeShellCommand("git rev-parse --abbrev-ref HEAD"));
    }
}
