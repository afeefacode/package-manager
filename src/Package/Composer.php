<?php

namespace Afeefa\Component\Package\Package;

use Webmozart\PathUtil\Path;

class Composer extends Package
{
    public $type = Package::TYPE_COMPOSER;

    protected function getPackageFile(): string
    {
        return Path::join($this->path, 'composer.json');
    }
}