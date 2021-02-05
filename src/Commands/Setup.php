<?php

namespace Afeefa\Component\Package\Commands;

use Afeefa\Component\Cli\Command;
use Afeefa\Component\Package\Actions\SetupPackage;
use Afeefa\Component\Package\Package\Package;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Webmozart\PathUtil\Path;

class Setup extends Command
{
    protected function setArguments()
    {
        $packages = $this->findPackagesToConfigure();

        $this
            ->addSelectableArgument(
                'package_name',
                [...array_keys($packages), 'all'],
                InputArgument::OPTIONAL,
                'The package to configure'
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

        $packages = $this->findPackagesToConfigure();

        $packages = $packageName === 'all' ? $packages : [$packages[$packageName]];

        foreach ($packages as $package) {
            $this->runAction(SetupPackage::class, [
                'package' => $package,
                'reset' => $this->getOption('reset')
            ]);
        }
    }

    private function findPackagesToConfigure(): array
    {
        // find all packages that can be installed
        $packages = [];

        // self package
        $package = Package::composer()->path(getcwd());
        if (file_exists(Path::join($package->path, '.afeefa', 'package', 'install.php'))) {
            $packages[$package->name] = $package;
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

        return $packages;
    }
}
