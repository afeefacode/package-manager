<?php

namespace Afeefa\Component\Package\Commands;

use Afeefa\Component\Cli\Command;
use Afeefa\Component\Package\Helpers;
use Afeefa\Component\Package\Package\Package;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Filesystem\Path;

class Setup extends Command
{
    protected function setArguments()
    {
        $packages = array_keys($this->findPackagesToInstall());
        if (count($packages)) {
            $packages[] = 'all';
        }

        $this
            ->addSelectableArgument(
                'package_name',
                $packages,
                count($packages) ? 'The package to configure' : 'No packages to configure found.'
            )
            ->addOption(
                'reset',
                null,
                InputOption::VALUE_NONE,
                'Reset and restart configuration',
                null
            );
    }

    protected function executeCommand()
    {
        $packageName = $this->getArgument('package_name');

        $packages = $this->findPackagesToInstall();

        $packages = $packageName === 'all' ? $packages : [$packages[$packageName]];

        /** @var Package */
        foreach ($packages as $package) {
            $InstallAction = $package->getInstallAction();
            if ($InstallAction) {
                $this->runAction($InstallAction, [
                    'package' => $package,
                    'reset' => $this->getOption('reset')
                ]);
            }
        }
    }

    private function findPackagesToInstall(): array
    {
        // find all packages that can be installed
        $packages = [];

        // root package
        $rootPackage = Helpers::getRootPackage();
        if ($rootPackage->hasInstallAction()) {
            $packages[$rootPackage->name] = $rootPackage;
        }

        // packages from vendor
        $composerVendorPath = Path::join(getcwd(), 'vendor');
        foreach (new \DirectoryIterator($composerVendorPath) as $composerVendorFileInfo) {
            if (!$composerVendorFileInfo->isDot() && $composerVendorFileInfo->isDir()) {
                $vendorPath = $composerVendorFileInfo->getRealPath();
                // all packages in vendor
                foreach (new \DirectoryIterator($vendorPath) as $packageFileInfo) {
                    if (!$packageFileInfo->isDot() && $packageFileInfo->isDir()) {
                        // package has .afeefa/package dir --> run setup
                        $package = Package::composer()->path($packageFileInfo->getRealPath());
                        if ($package->hasInstallAction()) {
                            // filter out copies such as package-backup
                            if ($package->name === $composerVendorFileInfo->getBasename() . '/' . $packageFileInfo->getBasename()) {
                                $packages[$package->name] = $package;
                            }
                        }
                    }
                }
            }
        }

        return $packages;
    }
}
