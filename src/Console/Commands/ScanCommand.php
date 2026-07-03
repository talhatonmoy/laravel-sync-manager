<?php

namespace DeployCar\LaravelSyncManager\Console\Commands;

use DeployCar\LaravelSyncManager\Contracts\FileScannerInterface;
use Illuminate\Console\Command;

class ScanCommand extends Command
{
    protected $signature = 'sync:scan';

    protected $description = 'Scan the configured source path and print the current file manifest.';

    public function handle(FileScannerInterface $scanner): int
    {
        $this->line(json_encode($scanner->scan(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
