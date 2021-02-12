<?php

namespace Afeefa\Component\Package;

use Afeefa\Component\Package\Package\Package;
use Webmozart\PathUtil\Path;

class Helpers
{
    public static function getRootPackage(): Package
    {
        return Package::composer()->path(getcwd());
    }

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
