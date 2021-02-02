<?php

use Afeefa\Component\Cli\Application;
use Afeefa\Component\Package\Commands\Install;
use Afeefa\Component\Package\Commands\Release;
use Afeefa\Component\Package\Helpers;

require_once getcwd() . '/vendor/autoload.php';

$version = Helpers::getVersion();
$packages = Helpers::getReleasePackages();

$infos = [
    'Project version' => $version ?: 'version not yet set'
];

$infos['Library versions'] = count($packages) ? '' : 'no packages defined yet';

foreach ($packages as $package) {
    $infos[' - ' . $package->name . " ($package->type)"] = "file: $package->version tag: $package->tag";
}

(new Application('Afeefa Package Manager'))

    ->runBefore(Install::class)

    ->command('release', Release::class, 'Release a new version')

    ->infos($infos)

    ->run();
