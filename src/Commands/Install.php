<?php

namespace Afeefa\Component\Package\Commands;

use Afeefa\Component\Cli\Command;
use Afeefa\Component\Package\Actions\InstallPackage;
use Afeefa\Component\Package\Helpers;
use Afeefa\Component\Package\Package\Package;
use Webmozart\PathUtil\Path;

class Install extends Command
{
    protected function executeCommand()
    {
        // find all packages that can be installed
        $packages = [];

        // self package
        $package = Package::composer()->path(getcwd());
        $packages[$package->name] = $package;

        // packages from vendor
        $composerVendorPath = Path::join(getcwd(), 'vendor');
        foreach (new \DirectoryIterator($composerVendorPath) as $composerVendorFileInfo) {
            if (!$composerVendorFileInfo->isDot() && $composerVendorFileInfo->isDir()) {
                $vendorPath = $composerVendorFileInfo->getRealPath();
                // all packages in vendor
                foreach (new \DirectoryIterator($vendorPath) as $packageFileInfo) {
                    if (!$packageFileInfo->isDot() && $packageFileInfo->isDir()) {
                        // package has .afeefa/package dir --> run setup
                        if (file_exists(Path::join($packageFileInfo->getRealPath(), '.afeefa', 'package', 'install.php'))) {
                            $package = Package::composer()->path($packageFileInfo->getRealPath());
                            // filter out copies such as package-backup
                            if ($package->name === $composerVendorFileInfo->getBasename() . '/' . $packageFileInfo->getBasename()) {
                                $packages[$package->name] = $package;
                            }
                        }
                    }
                }
            }
        }

        foreach ($packages as $package) {
            $this->runActionWithoutTitle(InstallPackage::class, [
                'package' => $package
            ]);
        }

        // check release version

        $packages = Helpers::getReleasePackages();
        $version = Helpers::getVersion();

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
    }
}
