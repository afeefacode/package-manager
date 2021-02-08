<?php

namespace Afeefa\Component\Package;

use Afeefa\Component\Package\Actions\Install as PackageInstall;
use Afeefa\Component\Package\Files\Files;
use Webmozart\PathUtil\Path;

class Install extends PackageInstall
{
    protected $configFolderName = 'package';

    protected function install(): void
    {
        $this->createFiles([
            Files::file()
                ->path(Path::join($this->projectConfigPath, 'release', 'release.php'))
                ->template(Path::join($this->packageConfigPath, 'install', 'templates', 'release.php')),

            Files::file()
                ->path(Path::join($this->projectConfigPath, 'release', 'version.txt'))
                ->template(Path::join($this->packageConfigPath, 'install', 'templates', 'version.txt'))
        ]);
    }
}

return Install::class;
