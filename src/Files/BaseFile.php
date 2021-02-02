<?php

namespace Afeefa\Component\Package\Files;

class BaseFile
{
    public $path;
    public $writable = false;
    public $template;
    public $replacements;
    public $content;

    public function path($path): BaseFile
    {
        $this->path = $path;
        return $this;
    }

    public function exists(): bool
    {
        return file_exists($this->path);
    }

    public function writable(): BaseFile
    {
        $this->writable = true;
        return $this;
    }

    public function template(string $template, ?array $replacements = null): BaseFile
    {
        $this->template = $template;
        $this->replacements = $replacements;
        return $this;
    }

    public function content(string $content): BaseFile
    {
        $this->content = $content;
        return $this;
    }
}
