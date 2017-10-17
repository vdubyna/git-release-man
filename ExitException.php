<?php

namespace Mirocode\GitReleaseMan;


class ExitException extends \Exception
{
    const EXIT_MESSAGE = 'Stop the release process and exit.';
}