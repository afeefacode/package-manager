<?php

use Afeefa\Component\Cli\Application;
use Afeefa\Component\Package\Commands\Check;
use Afeefa\Component\Package\Commands\Release;
use Afeefa\Component\Package\Helpers;

require_once __DIR__ . '/../vendor/autoload.php';

$version = Helpers::getVersion();
$packages = Helpers::getPackages();

$infos = [
    'Project version' => $version ?: 'version not set'
];

foreach ($packages as $package) {
    $infos[$package->name . " ($package->type)"] = "file: $package->version tag: $package->tag";
}

(new Application('Afeefa Package Manager'))

    ->runBefore(Check::class, 'Check setup')

    ->command('release', Release::class, 'Release a new version')

    ->infos($infos)

    ->run();
