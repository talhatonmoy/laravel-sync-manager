<?php

namespace DeployCar\LaravelSyncManager\Services;

use DeployCar\LaravelSyncManager\Contracts\StateRepositoryInterface;
use DeployCar\LaravelSyncManager\Models\SyncTargetState;

class StateRepository implements StateRepositoryInterface
{
    public function forTarget(string $targetName): array
    {
        return SyncTargetState::query()
            ->where('target_name', $targetName)
            ->get(['path', 'hash', 'size', 'modified_at', 'manifest_id'])
            ->mapWithKeys(static fn ($file) => [
                $file->path => [
                    'hash' => $file->hash,
                    'size' => $file->size,
                    'modified_at' => optional($file->modified_at)->toIso8601String(),
                    'manifest_id' => $file->manifest_id,
                ],
            ])
            ->all();
    }

    public function latestManifestId(string $targetName): ?string
    {
        return SyncTargetState::query()
            ->where('target_name', $targetName)
            ->whereNotNull('manifest_id')
            ->latest('updated_at')
            ->value('manifest_id');
    }

    public function replace(string $targetName, array $state, ?string $manifestId = null): void
    {
        SyncTargetState::query()->where('target_name', $targetName)->delete();

        foreach ($state as $path => $file) {
            SyncTargetState::query()->create([
                'target_name' => $targetName,
                'path' => $path,
                'hash' => $file['hash'],
                'size' => $file['size'] ?? null,
                'modified_at' => $file['modified_at'] ?? null,
                'manifest_id' => $manifestId,
            ]);
        }
    }

    public function merge(string $targetName, array $changes, ?string $manifestId = null): array
    {
        $state = $this->forTarget($targetName);

        foreach ($changes as $file) {
            $state[$file['path']] = [
                'hash' => $file['hash'],
                'size' => $file['size'] ?? null,
                'modified_at' => $file['modified_at'] ?? now()->toIso8601String(),
                'manifest_id' => $manifestId,
            ];
        }

        $this->replace($targetName, $state, $manifestId);

        return $state;
    }
}
