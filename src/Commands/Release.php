<?php

namespace Afeefa\Component\Package\Commands;

use Afeefa\Component\Cli\Command;
use Afeefa\Component\Package\Helpers;
use Webmozart\PathUtil\Path;

class Release extends Command
{
    protected function executeCommand()
    {
        $packages = Helpers::getReleasePackages();
        $version = Helpers::getVersion();

        // require afeefa-package to be setup

        $installMarker = Path::join(getcwd(), '.afeefa', 'package', '.installed');
        if (!file_exists($installMarker)) {
            $this->abortCommand('Setup missing: Run afeefa-package setup afeefa/package-manager');
        }

        // require a version in composer.json or package.json

        foreach ($packages as $package) {
            if ($package->version === null) {
                $packageFile = $package->getPackageFile();
                $relativePackageFile = Path::makeRelative($packageFile, getcwd());
                $this->printInfo('There is no version field in ' . $relativePackageFile);
                $createVersionField = $this->printConfirm('Create that version field?');
                if ($createVersionField) {
                    $this->replaceInFile($packageFile, function ($content) use ($package, $version) {
                        $packageNamePattern = preg_quote($package->name, '/');
                        return preg_replace(
                            '/^(\s+)("name": "' . $packageNamePattern . '",\n)/m',
                            "$1$2$1\"version\": \"$version\",\n",
                            $content
                        );
                    });
                    $this->printBullet("Version field added in <info>$packageFile</info>");
                } else {
                    $this->abortCommand('Version field required');
                }
            }
        }

        // have everything committed beforehand

        try {
            $this->runProcess('test -z "$(git status --porcelain)"');
        } catch (\Exception $e) {
            $this->runProcess('git status');
            $this->abortCommand('You need to commit all changes prior to release.');
        }

        $version = Helpers::getVersion();

        $this->printText("Project version is: <info>$version</info> (<fg=blue>.afeefa/package/release/version.txt</>)");
        $this->printText('Library versions:');
        if (count($packages)) {
            foreach ($packages as $package) {
                $file = basename($package->getPackageFile());
                $this->printText(" - $package->name: <info>$package->version</info> (<fg=blue>$file</>) <info>$package->tag</info> (<fg=blue>git tag</>)");
            }
        } else {
            $this->printBullet('No packages defined yet in .afeefa/package/release.php');
        }

        // select next version

        [$major, $minor, $patch] = explode('.', $version);

        $nextMajor = ($major + 1) . '.0.0';
        $nextMinor = "$major." . ($minor + 1) . '.0';
        $nextPatch = "$major.$minor." . ($patch + 1);

        $choice = $this->printChoice('Increase version', [
            'Major -> ' . $nextMajor,
            'Minor -> ' . $nextMinor,
            'Patch -> ' . $nextPatch,
            'Custom -> ...'
        ], 'Patch -> ' . $nextPatch);

        $nextVersion = $version;
        $setVersion = false;

        if (preg_match('/Major/', $choice)) {
            $nextVersion = $nextMajor;
            $setVersion = $this->printConfirm("Increase major version from $version -> " . $nextMajor);

        } else if (preg_match('/Minor/', $choice)) {
            $nextVersion = $nextMinor;
            $setVersion = $this->printConfirm("Increase minor version from $version -> " . $nextMinor);

        } else if (preg_match('/Patch/', $choice)) {
            $nextVersion = $nextPatch;
            $setVersion = $this->printConfirm("Increase patch version from $version -> " . $nextPatch);

        } else {
            $nextVersion = $this->printQuestion('Type in a version to set', $nextPatch);
            $setVersion = $this->printConfirm("Increase patch version from $version -> " . $nextVersion);
        }

        if (!$setVersion) {
            $this->abortCommand();
        }

        $versionFile = Path::join(getcwd(), '.afeefa', 'package', 'release', 'version.txt');
        $versionFileRelative = Path::makeRelative($versionFile, getcwd());

        $this->printShellCommand("file_put_contents($versionFileRelative, '0.0.0')");
        file_put_contents($versionFile, "$nextVersion\n");

        $packages = Helpers::getReleasePackages();

        foreach ($packages as $package) {
            $package->setVersion($nextVersion);
        }

        $this->runProcess('git diff');

        $shouldCommit = $this->printConfirm('Shall these changes be committed and pushed to upstream?');

        if (!$shouldCommit) {
            $this->abortCommand();
        }

        $this->runProcess('git commit -am "set version: v' . $nextVersion . '"');
        $this->runProcess('git push');

        $this->runProcess('git tag v' . $nextVersion);
        $this->runProcess('git push origin v' . $nextVersion);
    }
}
