<?php

namespace DeployCar\LaravelSyncManager\Services;

class TargetResolver
{
    public function all(): array
    {
        $url = rtrim((string) config('sync.target.url'), '/');

        if ($url === '') {
            return [];
        }

        return [
            [
                'name' => (string) config('sync.target.name', parse_url($url, PHP_URL_HOST) ?: 'target'),
                'url' => $url,
                'api_key' => (string) config('sync.target.api_key'),
                'source_app_id' => (string) config('sync.target.source_app_id'),
                'is_default' => true,
            ],
        ];
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
