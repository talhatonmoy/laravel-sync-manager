<?php

namespace DeployCar\LaravelSyncManager\Services;

use DeployCar\LaravelSyncManager\Contracts\ChangeDetectorInterface;

class ChangeDetector implements ChangeDetectorInterface
{
    public function detect(array $sourceFiles, array $targetState): array
    {
        $changed = [];
        $unchanged = 0;

        foreach ($sourceFiles as $file) {
            $target = $targetState[$file['path']] ?? null;
            $status = $target === null ? 'add' : (($target['hash'] ?? null) === $file['hash'] ? 'unchanged' : 'modify');

            if ($status === 'unchanged') {
                $unchanged++;
                continue;
            }

            $changed[] = array_merge($file, ['status' => $status]);
        }

        return [
            'files' => $changed,
            'summary' => [
                'add' => count(array_filter($changed, static fn (array $file) => $file['status'] === 'add')),
                'modify' => count(array_filter($changed, static fn (array $file) => $file['status'] === 'modify')),
                'unchanged' => $unchanged,
                'delete_later' => count(array_diff(array_keys($targetState), array_column($sourceFiles, 'path'))),
                'total_files' => count($sourceFiles),
            ],
            'delete_later' => array_values(array_filter(array_map(
                static fn (string $path) => isset($targetState[$path]) ? [
                    'path' => $path,
                    'hash' => $targetState[$path]['hash'] ?? null,
                    'status' => 'delete_later',
                ] : null,
                array_diff(array_keys($targetState), array_column($sourceFiles, 'path'))
            ))),
        ];
    }

    public function preview(array $localFiles, array $remoteState): array
    {
        $localMap = collect($localFiles)->keyBy('path')->all();
        $overwriteLocal = [];
        $remoteOnly = [];
        $localOnly = [];
        $matching = [];

        foreach ($remoteState as $path => $remote) {
            $local = $localMap[$path] ?? null;

            if (! $local) {
                $remoteOnly[] = array_merge($remote, ['path' => $path, 'status' => 'production_only']);
                continue;
            }

            if (($local['hash'] ?? null) !== ($remote['hash'] ?? null)) {
                $overwriteLocal[] = [
                    'path' => $path,
                    'local_hash' => $local['hash'] ?? null,
                    'production_hash' => $remote['hash'] ?? null,
                    'status' => 'overwrite_local',
                ];
                continue;
            }

            $matching[] = array_merge($remote, ['path' => $path, 'status' => 'matching']);
        }

        foreach ($localFiles as $file) {
            if (! isset($remoteState[$file['path']])) {
                $localOnly[] = array_merge($file, ['status' => 'local_only']);
            }
        }

        return [
            'overwrite_local' => $overwriteLocal,
            'production_only' => $remoteOnly,
            'local_only' => $localOnly,
            'matching' => $matching,
            'summary' => [
                'overwrite_local' => count($overwriteLocal),
                'production_only' => count($remoteOnly),
                'local_only' => count($localOnly),
                'matching' => count($matching),
            ],
        ];
    }
}
