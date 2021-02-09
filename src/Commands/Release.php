<?php

namespace Afeefa\Component\Package\Commands;

use Afeefa\Component\Cli\Command;
use Afeefa\Component\Package\Helpers;
use Afeefa\Component\Package\Package\Package;
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

        // require a name in composer.json or package.json

        foreach ($packages as $package) {
            if (!$package->hasPackageFile()) {
                $packageFile = $package->getPackageFile();
                $relativePackageFile = Path::makeRelative($packageFile, getcwd());
                $this->abortCommand("No package file present: <info>$relativePackageFile</info>");
            }

            if ($package->name === null) {
                $packageFile = $package->getPackageFile();
                $relativePackageFile = Path::makeRelative($packageFile, getcwd());
                $this->printInfo('There is no name field in ' . $relativePackageFile);
                $packageName = $this->printQuestion('Create that name field? Just type in a package name:', 'afeefa/my-new-package');
                $createNameField = $this->printConfirm("$packageName Is this name okay?");
                if ($createNameField) {
                    $this->replaceInFile($packageFile, function ($content) use ($packageName) {
                        return preg_replace(
                            '/^\{/',
                            "{\n        \"name\": \"$packageName\",",
                            $content
                        );
                    });
                    $this->printBullet("Name field added in <info>$packageFile</info>");
                } else {
                    $this->abortCommand('Name field required');
                }
            }
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

        $this->checkPackageCopyClean(Package::composer()->path(getcwd()), getcwd());
        foreach ($packages as $package) {
            $this->checkPackageCopyClean($package, $package->path);
        }

        // print version info

        $version = Helpers::getVersion();

        $this->printText("Project version is: <info>$version</info> (<fg=blue>.afeefa/package/release/version.txt</>)");
        $this->printText('Library versions:');
        if (count($packages)) {
            foreach ($packages as $package) {
                $package = $package->getSplitPackage() ?: $package;

                $versionFile = Path::join($package->path, '.afeefa', 'package', 'release', 'version.txt');
                $packageVersion = '';
                if (file_exists($versionFile)) {
                    $packageVersion = '<info>' . trim(file_get_contents($versionFile)) . '</info> (<fg=blue>version.txt</>) ';
                }

                $file = basename($package->getPackageFile());
                $this->printText(" - $package->name: $packageVersion<info>$package->version</info> (<fg=blue>$file</>) <info>$package->tag</info> (<fg=blue>git tag</>)");
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

        // store version in version.txt

        $versionFile = Path::join(getcwd(), '.afeefa', 'package', 'release', 'version.txt');
        $versionFileRelative = Path::makeRelative($versionFile, getcwd());

        $this->printShellCommand("file_put_contents($versionFileRelative, '0.0.0')");
        file_put_contents($versionFile, "$nextVersion\n");

        // update packages

        $packages = Helpers::getReleasePackages();

        foreach ($packages as $package) {
            $this->printActionTitle("Update version of $package->name");

            // package version (if supported)

            $versionFile = Path::join($package->path, '.afeefa', 'package', 'release', 'version.txt');
            if (file_exists($versionFile)) {
                file_put_contents($versionFile, "$nextVersion\n");
            }

            // composer version

            $packageFile = $package->getPackageFile();
            $content = file_get_contents($packageFile);
            $content = preg_replace('/"version": ".+?"/', "\"version\": \"$nextVersion\"", $content);
            file_put_contents($packageFile, $content);

            $this->printBullet("$package->name: <info>$nextVersion</info>");
        }

        // show diffs

        $package = Package::composer()->path(getcwd());
        $this->printActionTitle("Diff for package $package->name");
        $this->runProcess('git --no-pager diff', $package->path);

        foreach ($packages as $package) {
            $this->printActionTitle("Diff for package $package->name");
            $this->runProcess('git --no-pager diff', $package->path);
        }

        $shouldCommit = $this->printConfirm('Shall these changes be committed and pushed to upstream?');

        if (!$shouldCommit) {
            $this->abortCommand();
        }

        // copy all split libraries

        foreach ($packages as $package) {
            if ($package->splitPath) {
                $this->runProcess('git reset HEAD --hard', $package->splitPath);
                $this->runProcess('git clean -fd', $package->splitPath);
                $this->checkPackageCopyClean($package, $package->splitPath);

                $rsync = <<<EOL
rsync -rtvuc
--exclude .git
--exclude vendor
--exclude node_modules
--delete
$package->path/ $package->splitPath/
EOL;

                $this->runProcess($rsync);
            }
        }

        // push new versions

        $this->runProcesses([
            'git add .',
            'git commit -m "set version: v' . $nextVersion . '"',
            'git push'
        ], getcwd());

        foreach ($packages as $package) {
            $packagePath = $package->splitPath ?: $package->path;

            $this->runProcesses([
                'git add .',
                'git commit -m "set version: v' . $nextVersion . '"',
                'git push',
                'git tag v' . $nextVersion,
                'git push origin v' . $nextVersion
            ], $packagePath);

            $this->printBullet("<info>Finish</info>: $package->name has now version $nextVersion");
        }
    }

    private function checkPackageCopyClean(Package $package, string $path)
    {
        try {
            $this->runProcess('test -z "$(git status --porcelain)"', $path);
        } catch (\Exception $e) {
            $this->runProcess('git status', $path);
            $this->abortCommand("Package $package->name has uncommited changes");
        }
    }
}
