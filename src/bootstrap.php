<?php

use Afeefa\Component\Cli\Cli;
use Afeefa\Component\Package\Commands\Release;
use Afeefa\Component\Package\Commands\Setup;

require_once getcwd() . '/vendor/autoload.php';

(new Cli('Afeefa Package Manager'))
    ->command('setup', Setup::class, 'Configure an installed package')
    ->command('release', Release::class, 'Release a new version of this package')
    ->run();
