<?php
/**
 * Created by PhpStorm.
 * User: vdubyna
 * Date: 8/16/17
 * Time: 02:31
 */

namespace Mirocode\GitReleaseMan\GitAdapter;


use Mirocode\GitReleaseMan\Entity\Feature;
use Mirocode\GitReleaseMan\Entity\MergeRequest;
use Mirocode\GitReleaseMan\Entity\Release;
use Mirocode\GitReleaseMan\ExitException;
use Mirocode\GitReleaseMan\Version;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class GitremoteAdapter extends GitAdapterAbstract implements GitAdapterInterface
{
    /**
     * Force Adapters to load feature info from repository
     *
     * @param $featureName
     *
     * @return Feature
     * @throws ExitException
     */
    public function buildFeature($featureName)
    {
        $feature = new Feature($featureName);
        $feature->setStatus(Feature::STATUS_NEW);

        if ($this->_isFeatureStarted($feature)) {
            $feature->setStatus(Feature::STATUS_STARTED);

            try {
                $process = new Process("git log -1 --pretty=format:\"%H\" origin/{$featureName}");
                $process->setWorkingDirectory(getcwd());
                $process->mustRun();
                $branchCommit = $process->getOutput();
                $feature->setCommit($branchCommit);
            } catch (ProcessFailedException $e) {
                throw new ExitException($e);
            }

            try {
                $process = new Process("git ls-remote --tags --refs origin | grep {$feature->getName()}");
                $process->setWorkingDirectory(getcwd());
                $process->mustRun();
                $tagsList = explode("\n", $process->getOutput());
                $tagsList = array_map(function ($tag) {
                    $tagParts = explode('/', $tag);
                    return end($tagParts);
                }, $tagsList);
            } catch (ProcessFailedException $e) {
                $tagsList = [];
            }
            foreach ($tagsList as $tagName) {
                if (0 === strpos($tagName, $this->getConfiguration()->getLabelForTest())) {
                    $feature->setStatus(Feature::STATUS_TEST);
                }

                if (0 === strpos($tagName, $this->getConfiguration()->getLabelForRelease())) {
                    $feature->setStatus(Feature::STATUS_RELEASE);
                }
            }
        }

        return $feature;
    }

    public function removeReleaseCandidates($release)
    {
        // TODO: Implement removeReleaseCandidates() method.
    }

    public function addLabelToFeature(Feature $feature, $label)
    {
        try {
            $testLabel = $label . "--{$feature->getName()}";
            $process = new Process("git tag -f {$testLabel} && git push origin {$testLabel}");
            $process->setWorkingDirectory(getcwd());
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            throw new ExitException($e);
        }

    }

    public function removeLabelsFromFeature(Feature $feature)
    {
        // TODO: Implement removeLabelsFromFeature() method.
    }

    public function getFeatureLabels(Feature $feature)
    {
        // TODO: Implement getFeatureLabels() method.
    }

    /**
     * @param Feature $feature
     *
     * @return bool
     */
    public function _isFeatureStarted(Feature $feature)
    {
        try {
            $process = new Process("git ls-remote --heads origin | grep {$feature->getName()}");
            $process->setWorkingDirectory(getcwd());
            $process->mustRun();
            return true;
        } catch (ProcessFailedException $e) {
            return false;
        }
    }

    /**
     * @return Version
     */
    protected function getLatestVersion()
    {
        // TODO: Implement getLatestVersion() method.
    }

    /**
     * @param Release $release
     *
     * @return Release
     */
    public function startReleaseCandidate(Release $release)
    {
        // TODO: Implement startReleaseCandidate() method.
    }

    public function pushFeatureIntoRelease(Release $release, Feature $feature)
    {
        // TODO: Implement pushFeatureIntoRelease() method.
    }

    /**
     * @param Feature $feature
     *
     * @return Feature
     */
    public function closeFeature(Feature $feature)
    {
        try {
            $process = new Process("git push -d origin {$feature->getName()}");
            $process->setWorkingDirectory(getcwd());
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            throw new ExitException($e);
        }
        $feature->setStatus(Feature::STATUS_CLOSE);
        return $feature;
    }

    /**
     * @param Feature $feature
     *
     * @return MergeRequest
     */
    public function getMergeRequestByFeature(Feature $feature)
    {
        // TODO: Implement getMergeRequestByFeature() method.
    }

    /**
     * @param Feature $feature
     *
     * @return MergeRequest
     */
    public function openMergeRequestByFeature(Feature $feature)
    {
        // TODO: Implement openMergeRequestByFeature() method.
    }

    /**
     *
     * @return Feature[]
     * @throws ExitException
     */
    public function getFeaturesList()
    {
        // Get list of tags
        try {
            $process = new Process('git fetch --all -q && git branch -r --list "origin/feature-*"');
            $process->setWorkingDirectory(getcwd());
            $process->mustRun();
            $features = $process->getOutput();
        } catch (ProcessFailedException $e) {
            throw new ExitException($e);
        }

        return array_map(function ($branchName) {
            $branchNameParts = explode('/', $branchName);
            return $this->buildFeature($branchNameParts[1]);
        }, array_filter(explode("\n", $features)));
    }

    public function getLatestReleaseStableTag()
    {
        // TODO: Implement getLatestReleaseStableTag() method.
    }

    public function getLatestReleaseCandidateTag()
    {
        // TODO: Implement getLatestReleaseCandidateTag() method.
    }

    /**
     * @param Feature $feature
     *
     * @return Feature
     */
    public function startFeature(Feature $feature)
    {
        if ($feature->getStatus() !== Feature::STATUS_NEW) {
            throw new ExitException("You can start feature only if it has status: NEW.");
        }

        // Check if branch does not exist locally
        if ($this->_isFeatureStarted($feature)) {
            throw new ExitException("Feature already exists");
        }
        // Create branch
        try {
            $process = new Process("git checkout master && git checkout -B {$feature->getName()}");
            $process->setWorkingDirectory(getcwd());
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            throw new ExitException($e);
        }
        // Push branch
        try {
            $process = new Process("git push origin {$feature->getName()}");
            $process->setWorkingDirectory(getcwd());
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            throw new ExitException($e);
        }

        try {
            $process = new Process("git log -1 --pretty=format:\"%H\" origin/{$feature->getName()}");
            $process->setWorkingDirectory(getcwd());
            $process->mustRun();
            $branchCommit = $process->getOutput();
        } catch(ProcessFailedException $e) {
            throw new ExitException($e);
        }

        $feature->setStatus(Feature::STATUS_STARTED)
                ->setCommit($branchCommit);

        return $feature;
    }

    /**
     * @param Release $release
     *
     * @return Release
     */
    public function createReleaseTag(Release $release, $metadata = '')
    {
        // TODO: Implement createReleaseTag() method.
    }

    /**
     * @param Release $release
     * @param Feature $feature
     *
     * @return void
     */
    public function pushFeatureIntoReleaseCandidate(Release $release, Feature $feature)
    {
        // TODO: Implement pushFeatureIntoReleaseCandidate() method.
    }
}