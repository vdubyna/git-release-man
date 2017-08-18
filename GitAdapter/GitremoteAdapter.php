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

    /**
     * @param Release $release
     *
     * @throws ExitException
     */
    public function removeReleaseCandidates(Release $release)
    {
        try {
            $process = new Process("git ls-remote --heads --refs origin | grep {$release->getVersion()}-RC");
            $process->setWorkingDirectory(getcwd());
            $process->mustRun();
            $items = explode("\n", $process->getOutput());
            $items = array_map(function ($tag) {
                $tagParts = explode('/', $tag);
                return end($tagParts);
            }, $items);
        } catch (ProcessFailedException $e) {
            $items = [];
        }

        foreach ($items as $item) {
            try {
                $process = new Process("git push -d origin {$item}");
                $process->setWorkingDirectory(getcwd());
                $process->mustRun();
            } catch (ProcessFailedException $e) {
                throw new ExitException($e);
            }
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
        try {
            $testLabel = $label . "--{$feature->getName()}";
            $process = new Process("git tag -f {$testLabel} && git push origin {$testLabel}");
            $process->setWorkingDirectory(getcwd());
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            throw new ExitException($e);
        }
    }

    /**
     * @param Feature $feature
     *
     * @throws ExitException
     */
    public function removeLabelsFromFeature(Feature $feature)
    {
        $labels = [$this->getConfiguration()->getLabelForTest(), $this->getConfiguration()->getLabelForRelease()];
        foreach ($labels as $label) {
            try {
                $testLabel = $label . "--{$feature->getName()}";
                $process = new Process("git push -d origin {$testLabel}");
                $process->setWorkingDirectory(getcwd());
                $process->mustRun();
            } catch (ProcessFailedException $e) {
                throw new ExitException($e);
            }
        }
    }

    /**
     * @param Feature $feature
     *
     * @return array
     */
    public function getFeatureLabels(Feature $feature)
    {
        try {
            $process = new Process("git ls-remote --tags --refs origin | grep {$feature->getName()}");
            $process->setWorkingDirectory(getcwd());
            $process->mustRun();
            $tagsList = explode("\n", $process->getOutput());
            $tagsList = array_map(function ($tag) {
                $tagParts = explode('/', $tag);
                return end($tagParts);
            }, $tagsList);
            $tagsList = array_filter($tagsList, function ($tagName) {
                if ((0 === strpos($tagName, $this->getConfiguration()->getLabelForTest()))
                    || (0 === strpos($tagName, $this->getConfiguration()->getLabelForRelease()))
                ) {
                    return true;
                } else {
                    return false;
                }
            });
        } catch (ProcessFailedException $e) {
            $tagsList = [];
        }
        return $tagsList;
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
        $filterRefs = function (array $list) {
            $items = array_map(function ($item) {
                $parts = explode('/', $item);

                return end($parts);
            }, $list);

            return array_filter($items, function ($name) {
                try {
                    Version::fromString($name);
                    return true;
                } catch (InvalidArgumentException $e) {
                    return false;
                }
            });
        };

        $versions = array_reduce(['tags', 'heads'], function ($versions, $versionType) use ($filterRefs) {
            try {
                $process = new Process("git ls-remote --{$versionType} --refs origin");
                $process->setWorkingDirectory(getcwd());
                $process->mustRun();
                $items = $filterRefs(explode("\n", $process->getOutput()));
            } catch (ProcessFailedException $e) {
                $items = [];
            }

            return array_merge($versions, $items);
        }, []);

        $version = (empty($versions)) ? Configuration::DEFAULT_VERSION : end(Semver::sort($versions));

        return Version::fromString($version);
    }

    /**
     * @param Release $release
     *
     * @return Release
     * @throws ExitException
     */
    public function startReleaseCandidate(Release $release)
    {
        try {
            $process = new Process(
                "git fetch origin && git checkout -B master " .
                "&& git checkout -B {$release->getBranch()} && git push origin {$release->getBranch()}"
            );
            $process->setWorkingDirectory(getcwd());
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            throw new ExitException($e);
        }

        $release->setStatus(Release::STATUS_STARTED);

        return $release;
    }

    public function pushFeatureIntoRelease(Release $release, Feature $feature)
    {
        try {
            $process = new Process(
                "git pull origin {$feature->getName()} && git push origin {$release->getBranch()}"
            );
            $process->setWorkingDirectory(getcwd());
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            throw new ExitException($e);
        }

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
        $filterRefs = function (array $list) {
            $items = array_map(function ($item) {
                $parts = explode('/', $item);

                return end($parts);
            }, $list);

            return array_filter($items, function ($name) {
                try {
                    Version::fromString($name);
                    return true;
                } catch (InvalidArgumentException $e) {
                    return false;
                }
            });
        };

        try {
            $process = new Process("git ls-remote --tags --refs origin");
            $process->setWorkingDirectory(getcwd());
            $process->mustRun();
            $tags = $filterRefs(explode("\n", $process->getOutput()));

            if (empty($tags)) {
                throw new ExitException("There is no stable version.");
            }

            $versions = array_filter($tags, function ($tag) {
                try {
                    $version = Version::fromString($tag);
                    return ($version->isStable()) ? true : false;
                } catch (InvalidArgumentException $e) {
                    return false;
                }
            });

            return (empty($versions)) ? Configuration::DEFAULT_VERSION : end(Semver::sort($versions));
        } catch (ProcessFailedException $e) {
            throw new ExitException($e);
        }
    }

    public function getLatestReleaseCandidateTag()
    {
        $filterRefs = function (array $list) {
            $items = array_map(function ($item) {
                $parts = explode('/', $item);

                return end($parts);
            }, $list);

            return array_filter($items, function ($name) {
                try {
                    Version::fromString($name);
                    return true;
                } catch (InvalidArgumentException $e) {
                    return false;
                }
            });
        };

        try {
            $process = new Process("git ls-remote --tags --refs origin");
            $process->setWorkingDirectory(getcwd());
            $process->mustRun();
            $tags = $filterRefs(explode("\n", $process->getOutput()));

            if (empty($tags)) {
                throw new ExitException("There is no stable version.");
            }

            $versions = array_filter($tags, function ($tag) {
                try {
                    $version = Version::fromString($tag);
                    return ($version->getStability() === Version::STABILITY_RC) ? true : false;
                } catch (InvalidArgumentException $e) {
                    return false;
                }
            });

            return (empty($versions)) ? Configuration::DEFAULT_VERSION : end(Semver::sort($versions));
        } catch (ProcessFailedException $e) {
            throw new ExitException($e);
        }
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
        $release->setMetadata($metadata); // TODO move to release object
        $releaseTag = (empty($metadata)) ? $release->getVersion() : $release->getVersion() . '+' . $metadata;
        try {
            $process = new Process("git tag {$releaseTag} && git push origin {$releaseTag}");
            $process->setWorkingDirectory(getcwd());
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            throw new ExitException($e);
        }

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
        try {
            $process = new Process(
                "git pull origin {$feature->getName()} && git push origin {$release->getBranch()}"
            );
            $process->setWorkingDirectory(getcwd());
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            throw new ExitException($e);
        }

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
            $process = new Process(
                "git checkout {$release->getBranch()} && " .
                "git pull origin {$feature->getName()}"
            );
            $process->setWorkingDirectory(getcwd());
            $process->mustRun();

            try {
                $process = new Process("git reset --hard ORIG_HEAD");
                $process->setWorkingDirectory(getcwd());
                $process->mustRun();
            } catch (ProcessFailedException $e) {
                throw new ExitException($e);
            }

            return true;
        } catch (ProcessFailedException $e) {
            try {
                $process = new Process("git merge --abort");
                $process->setWorkingDirectory(getcwd());
                $process->mustRun();
            } catch (ProcessFailedException $e) {
                throw new ExitException($e);
            }
            return false;
        }
    }
}