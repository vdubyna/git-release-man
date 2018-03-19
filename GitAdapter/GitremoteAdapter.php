<?php

namespace Mirocode\GitReleaseMan\GitAdapter;

use Composer\Semver\Semver;
use InvalidArgumentException;
use Mirocode\GitReleaseMan\Configuration;
use Mirocode\GitReleaseMan\Entity\Feature;
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

        if ($this->isFeatureStarted($feature)) {
            $feature->setStatus(Feature::STATUS_STARTED);

            $branchCommit = $this->execShellCommand("git log -1 --pretty=format:\"%H\" origin/{$featureName}");
            $feature->setCommit($branchCommit);

            foreach ($this->getListOfRefsByType('tags') as $tagName) {
                if (strpos($tagName, $feature->getName()) === false) {
                    continue;
                }

                if (0 === strpos($tagName, $this->getConfiguration()->getLabelForReleaseCandidate())) {
                    $feature->addLabel($this->getConfiguration()->getLabelForReleaseCandidate());
                    $feature->setStatus(Feature::STATUS_RELEASE_CANDIDATE);
                }

                if (0 === strpos($tagName, $this->getConfiguration()->getLabelForReleaseStable())) {
                    $feature->addLabel($this->getConfiguration()->getLabelForReleaseStable());
                    $feature->setStatus(Feature::STATUS_RELEASE_STABLE);
                }
            }
        }

        return $feature;
    }

    /**
     * @param Release $release
     *
     * @throws ExitException
     */
    public function removeReleaseCandidates(Release $release)
    {
        $releaseCandidateVersion = "{$release->getVersion()}-" . Version::STABILITY_RC;

        foreach ($this->getListOfRefsByType('heads') as $item) {
            if (strpos($item, $releaseCandidateVersion) === false) {
                continue;
            }
            $this->execShellCommand("git push -d origin {$item}");
        }
    }

    /**
     * @param Feature $feature
     * @param         $label
     *
     * @throws ExitException
     */
    public function addLabelToFeature(Feature $feature, $label)
    {
        $testLabel = $label . "--{$feature->getName()}";
        $this->execShellCommand("git tag -f {$testLabel} && git push origin {$testLabel}");
    }

    /**
     * @param Feature $feature
     *
     * @throws ExitException
     */
    public function removeLabelsFromFeature(Feature $feature)
    {
        $labels = [
            $this->getConfiguration()->getLabelForReleaseCandidate(),
            $this->getConfiguration()->getLabelForReleaseStable()
        ];

        foreach ($labels as $label) {
            $testLabel = $label . "--{$feature->getName()}";
            $this->execShellCommand("git tag -f {$testLabel} && git push origin {$testLabel}");
        }
    }

    /**
     * @param Feature $feature
     *
     * @return array
     */
    public function getFeatureLabels(Feature $feature)
    {
        return array_filter($this->getListOfRefsByType('tags'), function ($tagName) {
            if ((0 === strpos($tagName, $this->getConfiguration()->getLabelForReleaseCandidate()))
                || (0 === strpos($tagName, $this->getConfiguration()->getLabelForReleaseStable()))
            ) {
                return true;
            } else {
                return false;
            }
        });
    }

    /**
     * @param Feature $feature
     *
     * @return bool
     * @throws ExitException
     */
    public function isFeatureStarted(Feature $feature)
    {
        return !!$this->execShellCommand("git ls-remote --heads origin refs/heads/{$feature->getName()}", false);
    }

    /**
     * @return Version
     */
    protected function getLatestVersion()
    {
        $items = array_reduce(['tags', 'heads'], function ($versions, $versionType) {
            return array_merge($versions, $this->getListOfRefsByType($versionType));
        }, []);

        $versions = array_filter($items, function ($name) {
            try {
                Version::fromString($name);
                return true;
            } catch (InvalidArgumentException $e) {
                return false;
            }
        });

        $versions = Semver::sort($versions);
        $version  = (empty($versions)) ? Configuration::DEFAULT_VERSION : end($versions);

        return Version::fromString($version);
    }

    /**
     * @param $versionType
     *
     * @return array
     * @throws ExitException
     */
    protected function getListOfRefsByType($versionType)
    {
        $result = $this->execShellCommand("git ls-remote --{$versionType} --refs origin", []);
        $items  = array_map(function ($item) {
            $parts = explode('/', $item);
            return end($parts);
        }, explode("\n", $result));

        return $items;
    }

    /**
     * @param Release $release
     *
     * @return Release
     * @throws ExitException
     */
    public function startReleaseCandidate(Release $release)
    {
        $masterBranch = $this->getConfiguration()->getMasterBranch();
        $cmd = "git fetch origin && git checkout -B {$masterBranch} " .
            "&& git checkout -B {$release->getBranch()} && git push origin {$release->getBranch()}";
        $this->execShellCommand($cmd);
        $release->setStatus(Release::STATUS_STARTED);

        return $release;
    }

    /**
     * @param Release $release
     * @param Feature $feature
     *
     * @throws ExitException
     */
    public function pushFeatureIntoReleaseStable(Release $release, Feature $feature)
    {
        $this->execShellCommand("git pull origin {$feature->getName()} && git push origin {$release->getBranch()}");
        $release->addFeature($feature);
    }

    /**
     * @param Feature $feature
     *
     * @return Feature
     * @throws ExitException
     */
    public function closeFeature(Feature $feature)
    {
        $this->execShellCommand("git push -d origin {$feature->getName()}");
        $feature->setStatus(Feature::STATUS_CLOSED);

        return $feature;
    }

    /**
     *
     * @return Feature[]
     * @throws ExitException
     */
    public function getFeaturesList()
    {
        $features = $this->execShellCommand('git fetch --all -q && git branch -r --list "origin/feature-*"');

        return array_map(function ($featureName) {
            $featureNameParts = explode('/', $featureName);
            return $this->buildFeature($featureNameParts[1]);
        }, array_filter(explode("\n", $features)));
    }

    /**
     * @return string
     * @throws ExitException
     */
    public function getLatestReleaseStableTag()
    {
        $tags     = $this->getListOfRefsByType('tags');
        $versions = array_filter($tags, function ($tag) {
            try {
                $version = Version::fromString($tag);
                return ($version->isStable()) ? true : false;
            } catch (InvalidArgumentException $e) {
                return false;
            }
        });

        return (empty($versions)) ? Configuration::DEFAULT_VERSION : end(Semver::sort($versions));
    }

    /**
     * @return string
     * @throws ExitException
     */
    public function getLatestReleaseCandidateTag()
    {
        $tags     = $this->getListOfRefsByType('tags');
        $versions = array_filter($tags, function ($tag) {
            try {
                $version = Version::fromString($tag);
                return ($version->getStability() === Version::STABILITY_RC) ? true : false;
            } catch (InvalidArgumentException $e) {
                return false;
            }
        });

        return (empty($versions)) ? Configuration::DEFAULT_VERSION : end(Semver::sort($versions));
    }

    /**
     * @param Feature $feature
     *
     * @return Feature
     * @throws ExitException
     */
    public function startFeature(Feature $feature)
    {
        if ($feature->getStatus() !== Feature::STATUS_NEW) {
            throw new ExitException("You can start feature only if it has status: NEW.");
        }

        if ($this->isFeatureStarted($feature)) {
            throw new ExitException("Feature already exists");
        }

        $masterBranch = $this->getConfiguration()->getMasterBranch();

        $this->execShellCommand("git checkout {$masterBranch} && git checkout -B {$feature->getName()}");
        $this->execShellCommand("git push origin {$feature->getName()}");
        $branchCommit = $this->execShellCommand("git log -1 --pretty=format:\"%H\" origin/{$feature->getName()}");

        $feature->setStatus(Feature::STATUS_STARTED)
                ->setCommit($branchCommit);

        return $feature;
    }

    /**
     * @param Release $release
     *
     * @return Release
     * @throws ExitException
     */
    public function createReleaseTag(Release $release)
    {
        $this->execShellCommand("git tag {$release->getVersion()} && git push origin {$release->getVersion()}");
        return $release;
    }

    /**
     * @param Release $release
     * @param Feature $feature
     *
     * @return void
     * @throws ExitException
     */
    public function pushFeatureIntoReleaseCandidate(Release $release, Feature $feature)
    {
        $this->execShellCommand("git pull origin {$feature->getName()} && git push origin {$release->getBranch()}");
        $release->addFeature($feature);
    }

    /**
     * @param Feature $feature
     * @param Release $release
     *
     * @return bool
     * @throws ExitException
     */
    public function isFeatureReadyForRelease(Feature $feature, Release $release)
    {
        try {
            $this->execShellCommand("git checkout {$release->getBranch()} && git pull origin {$feature->getName()}");
            $this->execShellCommand("git reset --hard ORIG_HEAD");
            return true;
        } catch (ExitException $e) {
            $this->execShellCommand("git merge --abort");
            return false;
        }
    }

    /**
     * @param string $command
     *
     * @param null|mixed   $defaultValue
     *
     * @return string
     * @throws ExitException
     */
    protected function execShellCommand($command, $defaultValue = null)
    {
        try {
            $process = new Process($command);
            $process->setWorkingDirectory(getcwd());
            $process->mustRun();

            return $process->getOutput();
        } catch (ProcessFailedException $e) {
            if ($defaultValue === null) {
                throw new ExitException($e);
            }

            return $defaultValue;
        }
    }
}