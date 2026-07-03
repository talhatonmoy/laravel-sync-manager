<?php

namespace DeployCar\LaravelSyncManager\Exceptions;

use RuntimeException;

class SecurityViolationException extends RuntimeException
{
    protected string $path;

    public function __construct(string $path, string $message = 'Security violation detected.')
    {
        $this->path = $path;
        parent::__construct(sprintf("%s Path: %s", $message, $path));
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
