<?php

namespace Afeefa\Component\Package\Package;

use Afeefa\Component\Package\ReleaseManager;
use Symfony\Component\Process\Process;
use Webmozart\PathUtil\Path;

class Package
{
    const TYPE_COMPOSER = 'php';
    const TYPE_NPM = 'js';

    public $type;
    public $path;
    public $splitPath;

    public static function composer(): Composer
    {
        return new Composer();
    }

    public static function npm(): Npm
    {
        return new Npm();
    }

    public function type($type): Package
    {
        $this->type = $type;
        return $this;
    }

    public function path($path): Package
    {
        $this->path = $path;
        return $this;
    }

    public function split($splitPath): Package
    {
        $this->splitPath = $splitPath;
        return $this;
    }

    public function getSplitPackage(): ?Package
    {
        if ($this->splitPath) {
            return (new static())->path($this->splitPath);
        }
        return null;
    }

    public function hasInstallAction(): bool
    {
        $installFile = Path::join($this->path, '.afeefa', 'package', 'install', 'install.php');

        return file_exists($installFile);
    }

    public function getInstallAction(): ?string
    {
        $installFile = Path::join($this->path, '.afeefa', 'package', 'install', 'install.php');

        if (file_exists($installFile)) {
            return include $installFile;
        }

        return null;
    }

    public function __get($property)
    {
        if ($property === 'name') {
            return $this->getName();
        }

        if ($property === 'version') {
            return $this->getVersion();
        }

        if ($property === 'tag') {
            return $this->getTag();
        }
    }

    public function getReleaseManager(): ?ReleaseManager
    {
        $releaseFile = Path::join($this->path, '.afeefa', 'package', 'release.php');

        if (file_exists($releaseFile)) {
            return include $releaseFile;
        }

        return null;
    }

    public function getPackageFile(): string
    {
        return '';
    }

    public function hasPackageFile(): bool
    {
        return file_exists($this->getPackageFile());
    }

    protected function getName(): ?string
    {
        $json = $this->getPackageFileJson();
        return $json->name ?? null;
    }

    protected function getVersion(): ?string
    {
        $json = $this->getPackageFileJson();
        return $json->version ?? null;
    }

    protected function getTag(): string
    {
        $command = 'git describe --tags --abbrev=0';
        $process = Process::fromShellCommandline($command, $this->path);
        $process->run();
        return trim($process->getOutput());
    }

    protected function getPackageFileJson(): \stdClass
    {
        $content = file_get_contents($this->getPackageFile());
        return json_decode($content);
    }
}
