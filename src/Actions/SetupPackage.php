<?php

namespace Afeefa\Component\Package\Actions;

use Afeefa\Component\Cli\Action;
use Webmozart\PathUtil\Path;

class SetupPackage extends Action
{
    protected function getActionTitle()
    {
        $package = $this->getArgument('package');
        return 'Setup ' . $package->name;
    }

    protected function executeAction()
    {
        $package = $this->getArgument('package');
        $reset = $this->getArgument('reset');

        $installer = $package->getInstaller();

        if (!$installer) {
            return;
        }

        $missingFiles = $reset
            ? $installer->getFiles()
            : array_filter($installer->getFiles(), fn($file) => !$file->exists());

        if (count($missingFiles)) {
            $this->printInfo('The following files are missing:');
            $this->printList(array_map(function ($missingFile) {
                return Path::makeRelative($missingFile->path, getcwd());
            }, $missingFiles));
            $createFiles = $this->printConfirm('Create these files?');

            if ($createFiles) {
                foreach ($missingFiles as $missingFile) {
                    $dir = dirname($missingFile->path);
                    if (!file_exists($dir)) {
                        $this->runProcess("mkdir -p $dir");
                        $relativeDir = Path::makeRelative($dir, getcwd());
                        $this->printBullet("Folder created at <info>$relativeDir</info>");
                    }

                    $relativeFile = Path::makeRelative($missingFile->path, getcwd());

                    if ($missingFile->template) {
                        $this->runProcess("cp $missingFile->template $missingFile->path");
                    } else if ($missingFile->content) {
                        $this->printShellCommand("file_put_contents($missingFile->path, '$missingFile->content')");
                        file_put_contents($missingFile->path, $missingFile->content);
                    } else {
                        $this->runProcess("touch $missingFile->path");
                    }

                    $this->printBullet("File created at <info>$relativeFile</info>");
                }
            } else {
                $this->abortCommand('These files are required');
            }
        }

        $this->printBullet('<info>Finished</info>');
    }
}
