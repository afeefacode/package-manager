<?php

namespace Afeefa\Component\Package\Package;

use stdClass;
use Symfony\Component\Process\Process;
use Webmozart\PathUtil\Path;

class Package
{
    const TYPE_COMPOSER = 'php';
    const TYPE_NPM = 'js';

    public $type = null;
    public $path = null;

    public function type($type)
    {
        $this->type = $type;
        return $this;
    }

    public function path($path)
    {
        $this->path = $path;
        return $this;
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

    public function setVersion(string $version): void
    {
        $file = $this->getPackageFile();
        $content = file_get_contents($this->getPackageFile());
        $content = preg_replace('/"version": ".+?"/', "\"version\": \"$version\"", $content);
        file_put_contents($file, $content);
    }

    protected function getName(): string
    {
        $json = $this->getPackageFileJson();
        return $json->name;
    }

    protected function getVersion(): string
    {
        $json = $this->getPackageFileJson();
        return $json->version;
    }

    protected function getTag(): string
    {
        $command = 'git describe --tags --abbrev=0';
        $process = Process::fromShellCommandline($command, $this->path);
        $process->run();
        return trim($process->getOutput());
    }

    protected function getPackageFile(): string
    {
        return '';
    }

    protected function getPackageFileJson(): stdClass
    {
        $file = Path::join($this->getPackageFile());
        $content = file_get_contents($file);
        return json_decode($content);
    }
}
