<?php

namespace DeployCar\LaravelSyncManager\Console\Commands;

use DeployCar\LaravelSyncManager\Console\Concerns\CommandUX;
use DeployCar\LaravelSyncManager\Contracts\FileScannerInterface;
use Illuminate\Console\Command;

class ScanCommand extends Command
{
    use CommandUX;

    protected $signature = 'sync:scan';

    protected $description = 'Scan the configured source path and print the current file manifest.';

    public function handle(FileScannerInterface $scanner): int
    {
        try {
            $result = $scanner->scan();

            return $this->renderSuccess(['status' => 'success', 'files' => $result]);
        } catch (\Throwable $e) {
            return $this->renderError($e);
        }
    }
}
