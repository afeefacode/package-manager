<?php

namespace Afeefa\Component\Package;

class Installer
{
    protected $files = [];

    public function files(array $files): Installer
    {
        $this->files = $files;
        return $this;
    }

    public function getFiles(): array
    {
        return $this->files;
    }

}
