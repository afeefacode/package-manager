<?php

namespace Afeefa\Component\Package\Commands;

use Afeefa\Component\Cli\Command;
use Afeefa\Component\Package\Helpers;
use Afeefa\Component\Package\Package\Package;
use Symfony\Component\Filesystem\Path;

class Release extends Command
{
    protected function executeCommand()
    {
        $rootPackage = Helpers::getRootPackage();
        $releasePackages = Helpers::getReleasePackages();
        $version = Helpers::getVersion();

        // require afeefa/afeefa-package to be setup

        $installMarker = Path::join(getcwd(), '.afeefa', 'package', '.installed');
        if (!file_exists($installMarker)) {
            $this->abortCommand('Setup missing: Run afeefa-package setup afeefa/package-manager');
        }

        // require composer.json or package.json for each release package

        foreach ($releasePackages as $package) {
            if (!$package->hasPackageFile()) {
                $packageFile = $package->getPackageFile();
                $relativePackageFile = Path::makeRelative($packageFile, getcwd());
                $this->abortCommand("No package file present: <info>{$relativePackageFile}</info>");
            }
        }

        // require a name in root packages composer.json AND release packages composer.json or package.json

        $packagesToCheck = [$rootPackage, ...$releasePackages];
        foreach ($packagesToCheck as $package) {
            if ($package->name === null) {
                $packageFile = $package->getPackageFile();
                $relativePackageFile = Path::makeRelative($packageFile, getcwd());
                $this->printInfo('There is no name field in ' . $relativePackageFile);
                $packageName = $this->printQuestion('Create that name field? Just type in a package name:', 'afeefa/my-new-package');
                $createNameField = $this->printConfirm("{$packageName} Is this name okay?");
                if ($createNameField) {
                    $this->replaceInFile($packageFile, function ($content) use ($packageName) {
                        return preg_replace(
                            '/^\{/',
                            "{\n        \"name\": \"{$packageName}\",",
                            $content
                        );
                    });
                    $this->printBullet("Name field added in <info>{$packageFile}</info>");
                } else {
                    $this->abortCommand('Name field required');
                }
            }
        }

        // require a version in release packages composer.json or package.json

        foreach ($releasePackages as $package) {
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
                            "$1$2$1\"version\": \"{$version}\",\n",
                            $content
                        );
                    });
                    $this->printBullet("Version field added in <info>{$packageFile}</info>");
                } else {
                    $this->abortCommand('Version field required');
                }
            }
        }

        // have everything committed beforehand

        $this->checkPackageCopyClean($rootPackage, $rootPackage->path);
        foreach ($releasePackages as $package) {
            $packageIsOutsideWorkingCopy = !Path::isBasePath($rootPackage->path, $package->path);
            if ($packageIsOutsideWorkingCopy) {
                $this->checkPackageCopyClean($package, $package->path);
            }
        }

        // create release folders for split packages

        foreach ($releasePackages as $package) {
            if ($package->split) {
                $releaseFolder = $this->getPackageReleaseFolder($package);
                if (!file_exists($releaseFolder)) {
                    $this->printActionTitle("Create release folder for split package: {$package->name}");
                    $this->runProcess("mkdir -p {$releaseFolder}");
                    $this->runProcess("git clone {$package->splitRepo} .", $releaseFolder);

                    $relativeReleaseFolder = Path::makeRelative($releaseFolder, getcwd());
                    $this->printBullet("<info>Finish:</info> Created folder <fg=blue>{$relativeReleaseFolder}</>");
                }
            }
        }

        // print version info

        $this->printText("Project version is: <info>{$version}</info> (<fg=blue>.afeefa/package/release/version.txt</>)");
        $this->printText('Library versions:');
        if (count($releasePackages)) {
            foreach ($releasePackages as $package) {
                $package = $this->getReleasePackage($package);
                $file = basename($package->getPackageFile());
                $tag = $this->getTag($package->path);
                $this->printText(" - {$package->name}: <info>{$package->version}</info> (<fg=blue>{$file}</>) <info>{$tag}</info> (<fg=blue>git tag</>)");
            }
        } else {
            $this->printBullet('No packages defined yet in .afeefa/package/packages.php');
        }

        // select next version

        [$major, $minor, $patch] = explode('.', $version);

        $nextMajor = ($major + 1) . '.0.0';
        $nextMinor = "{$major}." . ($minor + 1) . '.0';
        $nextPatch = "{$major}.{$minor}." . ($patch + 1);

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
            $setVersion = $this->printConfirm("Increase major version from {$version} -> " . $nextMajor);
        } elseif (preg_match('/Minor/', $choice)) {
            $nextVersion = $nextMinor;
            $setVersion = $this->printConfirm("Increase minor version from {$version} -> " . $nextMinor);
        } elseif (preg_match('/Patch/', $choice)) {
            $nextVersion = $nextPatch;
            $setVersion = $this->printConfirm("Increase patch version from {$version} -> " . $nextPatch);
        } else {
            $nextVersion = $this->printQuestion('Type in a version to set', $nextPatch);
            $setVersion = $this->printConfirm("Increase patch version from {$version} -> " . $nextVersion);
        }

        if (!$setVersion) {
            $this->abortCommand();
        }

        // store version in version.txt

        $versionFile = Path::join(getcwd(), '.afeefa', 'package', 'release', 'version.txt');
        $versionFileRelative = Path::makeRelative($versionFile, getcwd());

        $this->printShellCommand("file_put_contents({$versionFileRelative}, '{$versionFile}')");
        file_put_contents($versionFile, "{$nextVersion}\n");

        // update package composer/package.json version

        foreach ($releasePackages as $package) {
            $this->printActionTitle("Update version of {$package->name}");

            $packageFile = $package->getPackageFile();
            $content = file_get_contents($packageFile);
            $content = preg_replace('/"version": ".+?"/', "\"version\": \"{$nextVersion}\"", $content);
            file_put_contents($packageFile, $content);

            $this->printBullet("{$package->name}: <info>{$nextVersion}</info>");
        }

        // show diffs before autocommit

        $this->printActionTitle("Diff for package {$rootPackage->name}");
        $this->runProcess('git --no-pager diff', $rootPackage->path);

        foreach ($releasePackages as $package) {
            $this->printActionTitle("Diff for package {$package->name}");
            $this->runProcess('git --no-pager diff', $package->path);
        }

        $shouldCommit = $this->printConfirm('Shall these changes be committed and pushed to upstream?');

        if (!$shouldCommit) {
            $this->abortCommand();
        }

        // copy all split libraries

        foreach ($releasePackages as $package) {
            if ($package->split) {
                $this->printActionTitle("Reset split package {$package->name}");

                $releaseFolder = $this->getPackageReleaseFolder($package);
                $this->runProcess('git reset HEAD --hard', $releaseFolder);
                $this->runProcess('git clean -fd', $releaseFolder);
                $this->runProcess('git pull --rebase', $releaseFolder);
                $this->checkPackageCopyClean($package, $releaseFolder);

                $rsync = <<<EOL
                    rsync -rtvc
                    --exclude .git
                    --exclude vendor
                    --exclude node_modules
                    --delete
                    {$package->path}/ {$releaseFolder}/
                    EOL;

                $this->runProcess($rsync);

                $this->printBullet("<info>Finish:</info> Split package {$package->name} reset");
            }
        }

        // push new versions

        $this->printActionTitle('Commit and push new versions to branches');

        $this->printSubActionTitle($rootPackage->name);

        $this->runProcesses([
            'git add .',
            'git commit -m "set version: v' . $nextVersion . '"',
            'git push'
        ], $rootPackage->path);

        foreach ($releasePackages as $package) {
            if ($package->path === $rootPackage->path) {
                $this->runProcesses([
                    'git tag v' . $nextVersion,
                    'git push origin v' . $nextVersion
                ], $package->path);
            }
        }

        $this->printBullet("<info>Finish</info>: {$rootPackage->name} has now version {$nextVersion}");

        foreach ($releasePackages as $package) {
            $packageIsOutsideWorkingCopy = $package->split || !Path::isBasePath($rootPackage->path, $package->path);
            if ($packageIsOutsideWorkingCopy) {
                $this->printSubActionTitle($package->name);

                $releaseFolder = $this->getPackageReleaseFolder($package);
                $this->runProcesses([
                    'git add .',
                    'git commit -m "set version: v' . $nextVersion . '"',
                    'git push',
                    'git tag v' . $nextVersion,
                    'git push origin v' . $nextVersion
                ], $releaseFolder);

                $this->printBullet("<info>Finish</info>: {$package->name} has now version {$nextVersion}");
            }
        }
    }

    private function checkPackageCopyClean(Package $package, string $path)
    {
        try {
            $this->runProcess('test -z "$(git status --porcelain)"', $path);
        } catch (\Exception $e) {
            $this->runProcess('git status', $path);
            $this->abortCommand("Package {$package->name} has uncommited changes");
        }
    }

    private function getPackageReleaseFolder(Package $package): string
    {
        if ($package->split) {
            return Path::join(getcwd(), '.afeefa', 'package', 'release', 'split-packages', $package->name);
        }
        return $package->path;
    }

    private function getTag(string $path): string
    {
        $command = 'git tag -l';
        $hasTag = !!trim($this->runProcessAndGetContents($command, $path));

        if ($hasTag) {
            $command = 'git describe --tags --abbrev=0';
            return trim($this->runProcessAndGetContents($command, $path));
        }
        return 'no tag yet';
    }

    private function getReleasePackage(Package $package): Package
    {
        if ($package->split) {
            $releaseFolder = $this->getPackageReleaseFolder($package);
            $Class = get_class($package);
            return (new $Class())->path($releaseFolder);
        }
        return $package;
    }
}
