<?php

namespace Mirocode\GitReleaseMan;

/**
 * Class ExitException
 * @package Mirocode\GitReleaseMan
 */
class ExitException extends \Exception
{
    const EXIT_MESSAGE = 'Stop the release process and exit.';
}
