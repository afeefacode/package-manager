<?php

namespace Afeefa\Component\Package\Commands;

use Afeefa\Component\Cli\Command;
use Afeefa\Component\Package\Helpers;
use Webmozart\PathUtil\Path;

class Check extends Command
{
    protected function executeCommand()
    {
        $releaseFolder = Path::join(getcwd(), '.afeefa', 'package');
        $releaseFolderRelative = Path::makeRelative($releaseFolder, getcwd());
        $createFolder = false;

        if (!file_exists($releaseFolder)) {
            $createFolder = $this->printConfirm("There is no $releaseFolderRelative folder, should one be created?");
        }

        if ($createFolder) {
            $this->runProcess("mkdir -p $releaseFolder");
            $this->printBullet("Folder created at <info>$releaseFolderRelative</info>");
        }

        $packagesFile = Path::join($releaseFolder, 'packages.php');
        $packagesFileRelative = Path::makeRelative($packagesFile, getcwd());
        $createPackagesFile = false;

        if (!file_exists($packagesFile)) {
            $createPackagesFile = $this->printConfirm("There is no $packagesFileRelative file, should one be created?");
        }

        if ($createPackagesFile) {
            $templateFile = Path::join(__DIR__, '..', 'templates', 'packages.php');
            $this->runProcess("cp $templateFile $packagesFile");
            $this->printBullet("Packages file created at <info>$packagesFileRelative</info>");
        }

        $versionFile = Path::join($releaseFolder, 'version.txt');
        $versionFileRelative = Path::makeRelative($versionFile, getcwd());
        $createVersionFile = false;

        if (!file_exists($versionFile)) {
            $createVersionFile = $this->printConfirm("There is no $versionFileRelative file, should one be created?");
        }

        if ($createVersionFile) {
            $this->printShellCommand("file_put_contents($versionFileRelative, '0.0.0')");
            file_put_contents($versionFile, '0.0.0');
            $this->printBullet("Version file created at <info>$versionFileRelative</info>");
        }

        $packages = Helpers::getPackages();

        if (!count($packages)) {
            $this->abortCommand("Please define at least 1 package in $packagesFileRelative");
        }
    }
}
