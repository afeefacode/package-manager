<?php

namespace Afeefa\Component\Package\Files;

class Files
{
    public static function file(): File
    {
        return new File();
    }

    public static function folder(): Folder
    {
        return new Folder();
    }
}
