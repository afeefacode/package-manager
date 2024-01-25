<?php

namespace Afeefa\Component\Package\Package;

use stdClass;
use Symfony\Component\Filesystem\Path;

class Package
{
    public const TYPE_COMPOSER = 'php';
    public const TYPE_NPM = 'js';

    public $type;
    public $path;
    public $splitRepo;

    public static function composer(): Composer
    {
        return new Composer();
    }

    public static function npm(): Npm
    {
        return new Npm();
    }

    public function path($path): Package
    {
        $this->path = $path;
        return $this;
    }

    public function split($splitRepo): Package
    {
        $this->splitRepo = $splitRepo;
        return $this;
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

        if ($property === 'split') {
            return !!$this->splitRepo;
        }
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

    protected function getPackageFileJson(): \stdClass
    {
        $packageFile = $this->getPackageFile();

        if (!file_exists($packageFile)) {
            return new stdClass();
        }

        $content = file_get_contents($this->getPackageFile());

        return json_decode($content);
    }
}
