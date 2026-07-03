<?php

namespace DeployCar\LaravelSyncManager\Services;

class TargetResolver
{
    public function all(): array
    {
        $targets = config('sync.targets', []);
        $primaryTarget = config('sync.target.url') ? [
            'name' => config('sync.target.name'),
            'url' => config('sync.target.url'),
            'api_key' => config('sync.target.api_key'),
            'source_app_id' => config('sync.target.source_app_id'),
            'is_default' => true,
        ] : null;

        if ($targets === [] && $primaryTarget) {
            $targets = [$primaryTarget];
        } elseif ($primaryTarget) {
            $exists = false;
            foreach ($targets as $index => $target) {
                if (($target['name'] ?? '') === $primaryTarget['name']) {
                    $targets[$index] = array_merge($primaryTarget, $target);
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                array_unshift($targets, $primaryTarget);
            }
        }

        return array_values(array_filter(array_map(function (array $target): ?array {
            $url = rtrim((string) ($target['url'] ?? ''), '/');

            if ($url === '') {
                return null;
            }

            return [
                'name' => (string) ($target['name'] ?? parse_url($url, PHP_URL_HOST) ?? 'target'),
                'url' => $url,
                'api_key' => (string) ($target['api_key'] ?? config('sync.target.api_key')),
                'source_app_id' => (string) ($target['source_app_id'] ?? config('sync.target.source_app_id')),
                'is_default' => (bool) ($target['is_default'] ?? false),
            ];
        }, $targets)));
    }

    public function first(): ?array
    {
        return $this->all()[0] ?? null;
    }

    public function find(string $name): ?array
    {
        foreach ($this->all() as $target) {
            if ($target['name'] === $name) {
                return $target;
            }
        }

        return null;
    }
}
