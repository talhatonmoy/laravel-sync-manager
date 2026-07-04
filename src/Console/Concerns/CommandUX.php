<?php

namespace DeployCar\LaravelSyncManager\Console\Concerns;

use RuntimeException;
use Throwable;

trait CommandUX
{
    protected function renderError(Throwable $exception): int
    {
        $message = $exception->getMessage() ?: 'An error occurred';
        $file = $exception->getFile();
        $line = $exception->getLine();

        $this->error("[ERROR] {$message}");

        if ($file && $line) {
            $this->line("  <fg=gray>File: {$file}:{$line}</>");
        }

        $tip = $this->suggestErrorFix($exception);
        if ($tip) {
            $this->line("  <fg=cyan>Tip: {$tip}</>");
        }

        return self::FAILURE;
    }

    protected function renderSuccess(array $result): int
    {
        $status = $result['status'] ?? 'unknown';
        $icon = $status === 'success' ? '✓' : '✗';

        $this->line("<fg=green>{$icon} {$status}</>");
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    protected function guardTimeout(float $startTime, float $maxSeconds): void
    {
        $elapsed = microtime(true) - $startTime;

        if ($elapsed > $maxSeconds) {
            throw new RuntimeException("Operation exceeded {$maxSeconds} seconds. Check server logs.");
        }
    }

    private function suggestErrorFix(Throwable $exception): ?string
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'No sync target')) {
            return "Set SYNC_MANAGER_TARGET_URL in .env";
        }

        if (str_contains($message, 'API signature invalid')) {
            return "Check SYNC_MANAGER_API_KEY matches receiver";
        }

        if (str_contains($message, 'Object not found')) {
            return "Receiver object store may be corrupted; check storage logs";
        }

        if (str_contains($message, 'timed out')) {
            return "Network timeout; increase SYNC_MANAGER_TIMEOUT in .env";
        }

        if (str_contains($message, 'Connection refused')) {
            return "Receiver is unreachable; check SYNC_MANAGER_TARGET_URL";
        }

        return "Check storage/logs/laravel.log for details";
    }
}
