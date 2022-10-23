<?php

namespace Afeefa\Component\Package\Actions;

use Afeefa\Component\Cli\Action;
use Afeefa\Component\Package\Package\Package;
use Symfony\Component\Filesystem\Path;

class Install extends Action
{
    /**
     * @var Package
     */
    protected $package;

    /**
     * @var string
     */
    protected $packageRoot;

    /**
     * @var string
     */
    protected $packageConfigPath;

    /**
     * @var string
     */
    protected $projectRoot;

    /**
     * @var string
     */
    protected $projectConfigPath;

    /**
     * @var string
     */
    protected $configFolderName;

    protected function getActionTitle()
    {
        $this->package = $this->getArgument('package');
        return 'Setup ' . $this->package->name;
    }

    protected function executeAction()
    {
        $this->package = $this->getArgument('package');

        $this->packageRoot = $this->package->path;
        $this->packageConfigPath = Path::join($this->packageRoot, '.afeefa', $this->configFolderName);

        $this->projectRoot = getcwd();
        $this->projectConfigPath = Path::join($this->projectRoot, '.afeefa', $this->configFolderName);

        $installMarker = Path::join($this->projectConfigPath, '.installed');

        $reset = $this->getArgument('reset');

        if (file_exists($installMarker) && !$reset) {
            $this->printBullet('<info>Already set up</info>');
        } else {
            $this->install();
            $this->runProcess("touch {$installMarker}");
            $this->printBullet('<info>Finished</info>');
        }
    }

    protected function install(): void
    {
    }

    protected function createFiles(array $files)
    {
        foreach ($files as $file) {
            $dir = dirname($file->path);
            if (!file_exists($dir)) {
                $this->runProcess("mkdir -p {$dir}");
                $relativeDir = Path::makeRelative($dir, getcwd());
                $this->printBullet("Folder created at <info>{$relativeDir}</info>");
            }

            $relativeFile = Path::makeRelative($file->path, getcwd());

            if ($file->template) {
                $this->runProcess("cp {$file->template} {$file->path}");
            } elseif ($file->content) {
                $this->printShellCommand("file_put_contents({$file->path}, '{$file->content}')");
                file_put_contents($file->path, $file->content);
            } else {
                $this->runProcess("touch {$file->path}");
            }

            $this->printBullet("File created at <info>{$relativeFile}</info>");
        }
    }
}
