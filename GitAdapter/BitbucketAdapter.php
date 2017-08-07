<?php

namespace Mirocode\GitReleaseMan\GitAdapter;

use Bitbucket\API\Http\Listener\BasicAuthListener;
use Bitbucket\API\Repositories\Refs\Branches;
use Composer\Semver\Semver;
use InvalidArgumentException;
use Mirocode\GitReleaseMan\Entity\Feature;
use Mirocode\GitReleaseMan\Entity\MergeRequest;
use Mirocode\GitReleaseMan\Entity\Release;
use Mirocode\GitReleaseMan\GitAdapter\GitAdapterAbstract;
use Mirocode\GitReleaseMan\GitAdapter\GitAdapterInterface;
use Mirocode\GitReleaseMan\Configuration;
use Mirocode\GitReleaseMan\Version;

class BitbucketAdapter extends GitAdapterAbstract implements GitAdapterInterface
{

    /**
     * @return array|Branches|\Buzz\Message\MessageInterface|mixed
     */
    public function getFeaturesList()
    {
        $username   = $this->getConfiguration()->getUsername();
        $token      = $this->getConfiguration()->getToken();
        $repository = $this->getConfiguration()->getRepository();

        $branches = new Branches();
        $branches->getClient()->addListener(
            new BasicAuthListener($username, $token)
        );

        $branches = $branches->all($username, $repository);
        $branches = json_decode($branches->getContent(), true);
        $branches = $branches['values'];

        if (empty($branches)) {
            return array();
        }

        $branches = array_map(function ($branch) {
            return $branch['name'];
        }, $branches);

        $branches = array_filter($branches, function ($branch) {
            return (strpos($branch, 'feature') === 0);
        });

        $features = array_map(function ($branch) {
            return $this->buildFeature($branch);
        }, $branches);

        return $features;
    }

    /**
     * Force Adapters to load feature info from repository
     *
     * @param $featureName
     *
     * @return Feature
     */
    public function buildFeature($featureName)
    {
        $username    = $this->getConfiguration()->getUsername();
        $repository  = $this->getConfiguration()->getRepository();

        try {
            $featureInfo = $this->getApiClient()->repository()->branches($username, $repository, $featureName);
        } catch (\Github\Exception\RuntimeException $e) {
            $featureInfo = array();
        }

        $feature = new Feature($featureName);

        if (empty($featureInfo)) {
            $feature->setStatus(Feature::STATUS_NEW);
        } else {
            $feature->setStatus(Feature::STATUS_STARTED)
                    ->setCommit($featureInfo['commit']['sha']);

            $mergeRequest = $this->getMergeRequestByFeature($feature);

            if ($mergeRequest && $mergeRequest->getNumber()) {
                $labels = $this->getLabelsByMergeRequest($mergeRequest->getNumber());
                $feature->setLabels($labels)
                        ->setMergeRequestNumber($mergeRequest->getNumber());

                if (in_array($this->getConfiguration()->getLabelForTest(), $feature->getLabels())) {
                    $feature->setStatus(Feature::STATUS_TEST);
                }

                if (in_array($this->getConfiguration()->getLabelForRelease(), $feature->getLabels())) {
                    $feature->setStatus(Feature::STATUS_RELEASE);
                }
            }
        }
    }

    public function removeReleaseCandidates($release)
    {
        // TODO: Implement removeReleaseCandidates() method.
    }

    public function getMergeRequestsByLabel($label)
    {
        // TODO: Implement getMergeRequestsByLabel() method.
    }

    public function addLabelToFeature(Feature $feature, $label)
    {
        // TODO: Implement addLabelToFeature() method.
    }

    public function removeLabelsFromFeature(Feature $feature)
    {
        // TODO: Implement removeLabelsFromFeature() method.
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

    /**
     * @param Feature $feature
     *
     * @return Feature
     */
    public function closeFeature(Feature $feature)
    {
        // TODO: Implement closeFeature() method.
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

    public function getLatestReleaseStableTag()
    {
        // TODO: Implement getLatestReleaseTag() method.
    }

    public function getLatestReleaseCandidateTag()
    {
        // TODO: Implement getLatestTestReleaseTag() method.
    }

    /**
     * @param Feature $feature
     *
     * @return Feature
     */
    public function startFeature(Feature $feature)
    {
        // TODO: Implement startFeature() method.
    }

    /**
     * @return Version
     */
    public function getReleaseCandidateVersion()
    {
        // TODO: Implement getReleaseCandidateVersion() method.
    }

    /**
     * @return Version
     */
    public function getReleaseStableVersion()
    {
        // TODO: Implement getReleaseStableVersion() method.
    }
}