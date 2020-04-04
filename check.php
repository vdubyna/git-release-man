<?php

use Mirocode\GitReleaseMan\Version;

require_once 'Version.php';


$version = Version::fromString('1.0.1');
echo $version->increase('rc', 'master-stream+2001-01-12') . PHP_EOL;
$version = Version::fromString($version->increase('rc', 'dev-stream'));
echo $version->increase('rc', 'dev-stream');