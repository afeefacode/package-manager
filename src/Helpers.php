<?php

namespace Afeefa\Component\Package;

use Webmozart\PathUtil\Path;

class Helpers
{
    public static function getReleasePackages(): array
    {
        $release = Path::join(getcwd(), '.afeefa', 'package', 'release.php');

        if (file_exists($release)) {
            $releaseManager = include $release;
            return $releaseManager->getPackages();
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
