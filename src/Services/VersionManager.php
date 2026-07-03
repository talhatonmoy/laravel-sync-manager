<?php

namespace DeployCar\LaravelSyncManager\Services;

use DeployCar\LaravelSyncManager\Contracts\VersionManagerInterface;
use DeployCar\LaravelSyncManager\Contracts\StateRepositoryInterface;
use DeployCar\LaravelSyncManager\Models\SyncVersion;
use Illuminate\Support\Arr;

class VersionManager implements VersionManagerInterface
{
    public function createVersion(array $attributes): SyncVersion
    {
        return SyncVersion::query()->create($attributes);
    }

    public function updateStatus(SyncVersion $version, string $status, array $attributes = []): SyncVersion
    {
        $version->fill(array_merge($attributes, [
            'status' => $status,
            'completed_at' => in_array($status, ['success', 'failed'], true) ? now() : $version->completed_at,
        ]));
        $version->save();

        return $version->refresh();
    }

    public function replaceFiles(SyncVersion $version, array $files): void
    {
        $version->files()->delete();

        foreach ($files as $file) {
            $version->files()->create([
                'path' => $file['path'],
                'hash' => $file['hash'] ?? null,
                'size' => $file['size'] ?? null,
                'status' => $file['status'] ?? 'synced',
                'modified_at' => $file['modified_at'] ?? null,
                'metadata' => Arr::except($file, ['path', 'hash', 'size', 'status', 'modified_at']),
            ]);
        }
    }

    public function log(?SyncVersion $version, string $level, string $stage, string $message, array $context = []): void
    {
        $version?->logs()->create([
            'level' => $level,
            'stage' => $stage,
            'message' => $message,
            'context' => $context,
            'created_at' => now(),
        ]);
    }

    public function latestSuccessful(?string $operation = null): ?SyncVersion
    {
        return SyncVersion::query()
            ->when($operation, static fn ($query) => $query->where('operation', $operation))
            ->where('status', 'success')
            ->latest('id')
            ->first();
    }

    public function currentState(): array
    {
        $targetName = (string) config('sync.target.name');
        $tracked = app(StateRepositoryInterface::class)->forTarget($targetName);
        if ($tracked !== []) {
            return $tracked;
        }

        $version = $this->latestSuccessful('sync');

        if (! $version) {
            return [];
        }

        return $version->files()
            ->get(['path', 'hash', 'size', 'status', 'modified_at'])
            ->mapWithKeys(static fn ($file) => [
                $file->path => [
                    'hash' => $file->hash,
                    'size' => $file->size,
                    'status' => $file->status,
                    'modified_at' => optional($file->modified_at)->toIso8601String(),
                ],
            ])
            ->all();
    }
}
