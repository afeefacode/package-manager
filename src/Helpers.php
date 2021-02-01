<?php

namespace Afeefa\Component\Package;

use Webmozart\PathUtil\Path;

class Helpers
{
    public static function getPackages(): array
    {
        $packageFile = Path::join(getcwd(), '.afeefa', 'package', 'packages.php');

        if (file_exists($packageFile)) {
            return include $packageFile;
        }

        return [];
    }

    public static function getVersion(): string
    {
        $versionFile = Path::join(getcwd(), '.afeefa', 'package', 'version.txt');

        if (file_exists($versionFile)) {
            return trim(file_get_contents($versionFile));
        }

        return '';
    }
}
