<?php

namespace DeployCar\LaravelSyncManager\Services;

use DeployCar\LaravelSyncManager\Contracts\ManifestRepositoryInterface;
use DeployCar\LaravelSyncManager\Models\SyncManifest;
use Illuminate\Support\Arr;

class ManifestRepository implements ManifestRepositoryInterface
{
    public function create(array $attributes, array $files): SyncManifest
    {
        $manifest = SyncManifest::query()->create($attributes);

        foreach ($files as $file) {
            $manifest->files()->create([
                'path' => $file['path'],
                'hash' => $file['hash'],
                'size' => $file['size'] ?? null,
                'status' => $file['status'] ?? 'modify',
                'modified_at' => $file['modified_at'] ?? null,
                'metadata' => Arr::except($file, ['path', 'hash', 'size', 'status', 'modified_at']),
            ]);
        }

        return $manifest->fresh(['files']);
    }

    public function latestForTarget(string $targetName, string $direction = 'outgoing'): ?SyncManifest
    {
        return SyncManifest::query()
            ->where('target_name', $targetName)
            ->where('direction', $direction)
            ->latest('id')
            ->first();
    }

    public function attachVersion(string $manifestId, int $syncVersionId): void
    {
        SyncManifest::query()->where('manifest_id', $manifestId)->update([
            'sync_version_id' => $syncVersionId,
        ]);
    }

    public function filesForManifest(string $manifestId): array
    {
        $manifest = SyncManifest::query()->where('manifest_id', $manifestId)->with('files')->first();

        return $manifest?->files->map(fn ($file) => [
            'path' => $file->path,
            'hash' => $file->hash,
            'size' => $file->size,
            'status' => $file->status,
            'modified_at' => optional($file->modified_at)->toIso8601String(),
        ])->all() ?? [];
    }
}
