<?php

namespace Afeefa\Component\Package;

use Webmozart\PathUtil\Path;

class Helpers
{
    public static function getReleasePackages(): array
    {
        $packages = Path::join(getcwd(), '.afeefa', 'package', 'release', 'packages.php');

        if (file_exists($packages)) {
            return include $packages;
        }

        return [];
    }

    public static function getVersion(): string
    {
        $versionFile = Path::join(getcwd(), '.afeefa', 'package', 'release', 'version.txt');

        if (file_exists($versionFile)) {
            return trim(file_get_contents($versionFile));
        }

        return '';
    }
}
