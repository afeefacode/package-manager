<?php

use Afeefa\Component\Package\Files\Files;
use Afeefa\Component\Package\Installer;
use Webmozart\PathUtil\Path;

$packageManagerRoot = Path::join(__DIR__, '..', '..');
$projectPackageFolder = Path::join(getcwd(), '.afeefa', 'package');

return (new Installer())
    ->files([
        Files::file()
            ->path(Path::join($projectPackageFolder, 'release.php'))
            ->template(Path::join($packageManagerRoot, 'src', 'templates', 'release.php')),

        Files::file()
            ->path(Path::join($projectPackageFolder, 'version.txt'))
            ->content("0.0.0\n")
    ]);
