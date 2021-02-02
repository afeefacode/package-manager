<?php

namespace Afeefa\Component\Package;

class ReleaseManager
{
    protected $packages = [];

    public function packages(array $packages): ReleaseManager
    {
        $this->packages = $packages;
        return $this;
    }

    public function getPackages(): array
    {
        return $this->packages;
    }
}
