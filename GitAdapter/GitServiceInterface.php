<?php
/**
 * Created by PhpStorm.
 * User: vdubyna
 * Date: 8/18/17
 * Time: 12:51
 */

namespace Mirocode\GitReleaseMan\GitAdapter;


use Mirocode\GitReleaseMan\Entity\Feature;
use Mirocode\GitReleaseMan\Entity\MergeRequest;

interface GitServiceInterface
{

    /**
     * @param Feature $feature
     *
     * @return MergeRequest
     */
    public function getMergeRequestByFeature(Feature $feature);

    /**
     * @param Feature $feature
     *
     * @return MergeRequest
     */
    public function openMergeRequestByFeature(Feature $feature);

}