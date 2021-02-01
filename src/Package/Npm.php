<?php

namespace Afeefa\Component\Package\Package;

use Webmozart\PathUtil\Path;

class Npm extends Package
{
    public $type = Package::TYPE_NPM;

    protected function getPackageFile(): string
    {
        return Path::join($this->path, 'package.json');
    }
}
