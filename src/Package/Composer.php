<?php

namespace Afeefa\Component\Package\Package;

use Symfony\Component\Filesystem\Path;

class Composer extends Package
{
    public $type = Package::TYPE_COMPOSER;

    public function getPackageFile(): string
    {
        return Path::join($this->path, 'composer.json');
    }
}
