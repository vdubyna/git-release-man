<?php
/**
 * Created by PhpStorm.
 * User: vdubyna
 * Date: 7/3/17
 * Time: 17:59
 */

namespace Mirocode\GitReleaseMan;


class ExitException extends \Exception
{
    const EXIT_MESSAGE = 'Stop the release process and exit.';
}