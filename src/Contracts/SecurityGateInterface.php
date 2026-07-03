<?php

namespace DeployCar\LaravelSyncManager\Contracts;

interface SecurityGateInterface
{
    /**
     * Asserts that a file path is safe to be synchronized.
     *
     * @param string $path
     * @return void
     *
     * @throws \DeployCar\LaravelSyncManager\Exceptions\SecurityViolationException
     */
    public function assertSafe(string $path): void;
}
