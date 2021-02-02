<?php

namespace Afeefa\Component\Package\Commands;

use Afeefa\Component\Cli\Command;
use Afeefa\Component\Package\Helpers;
use Webmozart\PathUtil\Path;

class Release extends Command
{
    protected function executeCommand()
    {
        try {
            $this->runProcess('test -z "$(git status --porcelain)"');
        } catch (\Exception $e) {
            $this->runProcess('git status');
            $this->abortCommand('You need to commit all changes prior to release.');
        }

        $version = Helpers::getVersion();

        $this->printText('Current project version is: <info>' . $version . '</info>');

        [$major, $minor, $patch] = explode('.', $version);

        $nextMajor = ($major + 1) . '.0.0';
        $nextMinor = "$major." . ($minor + 1) . '.0';
        $nextPatch = "$major.$minor." . ($patch + 1);

        $choice = $this->printChoice('Increase version', [
            'Major -> ' . $nextMajor,
            'Minor -> ' . $nextMinor,
            'Patch -> ' . $nextPatch,
            'Custom -> ...'
        ], 'Patch -> ' . $nextPatch);

        $nextVersion = $version;
        $setVersion = false;

        if (preg_match('/Major/', $choice)) {
            $nextVersion = $nextMajor;
            $setVersion = $this->printConfirm("Increase major version from $version -> " . $nextMajor);

        } else if (preg_match('/Minor/', $choice)) {
            $nextVersion = $nextMinor;
            $setVersion = $this->printConfirm("Increase minor version from $version -> " . $nextMinor);

        } else if (preg_match('/Patch/', $choice)) {
            $nextVersion = $nextPatch;
            $setVersion = $this->printConfirm("Increase patch version from $version -> " . $nextPatch);

        } else {
            $nextVersion = $this->printQuestion('Type in a version to set', $nextPatch);
            $setVersion = $this->printConfirm("Increase patch version from $version -> " . $nextVersion);
        }

        if (!$setVersion) {
            $this->abortCommand();
        }

        $versionFile = Path::join(getcwd(), '.afeefa', 'package', 'version.txt');
        $versionFileRelative = Path::makeRelative($versionFile, getcwd());

        $this->printShellCommand("file_put_contents($versionFileRelative, '0.0.0')");
        file_put_contents($versionFile, "$nextVersion\n");

        $packages = Helpers::getReleasePackages();

        foreach ($packages as $package) {
            $package->setVersion($nextVersion);
        }

        $this->runProcess('git diff');

        $shouldCommit = $this->printConfirm('Shall these changes be committed and pushed to upstream?');

        if (!$shouldCommit) {
            $this->abortCommand();
        }

        $this->runProcess('git commit -am "set version: v' . $nextVersion . '"');
        $this->runProcess('git push');

        $this->runProcess('git tag v' . $nextVersion);
        $this->runProcess('git push origin v' . $nextVersion);
    }
}
