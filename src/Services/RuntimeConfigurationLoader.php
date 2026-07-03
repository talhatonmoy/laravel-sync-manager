<?php

namespace DeployCar\LaravelSyncManager\Services;

use DeployCar\LaravelSyncManager\Models\SyncSetting;
use DeployCar\LaravelSyncManager\Models\SyncTarget;
use Illuminate\Support\Facades\Schema;

class RuntimeConfigurationLoader
{
    public function load(): void
    {
        $this->loadTargets();
        $this->loadSettings();
    }

    protected function loadTargets(): void
    {
        if (! Schema::hasTable('sync_targets')) {
            return;
        }

        $targets = SyncTarget::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(static function (SyncTarget $target): array {
                return [
                    'name' => $target->name,
                    'url' => $target->url,
                    'api_key' => $target->api_key,
                    'source_app_id' => $target->source_app_id,
                    'is_default' => $target->is_default,
                    'metadata' => $target->metadata ?? [],
                ];
            })
            ->values()
            ->all();

        if ($targets === []) {
            return;
        }

        config()->set('sync.targets', $targets);

        $defaultTarget = collect($targets)->firstWhere('is_default', true) ?? $targets[0];
        config()->set('sync.target', array_merge(config('sync.target', []), [
            'name' => $defaultTarget['name'],
            'url' => $defaultTarget['url'],
            'api_key' => $defaultTarget['api_key'],
            'source_app_id' => $defaultTarget['source_app_id'] ?: config('sync.target.source_app_id'),
        ]));
    }

    protected function loadSettings(): void
    {
        if (! Schema::hasTable('sync_settings')) {
            return;
        }

        $map = [
            'default_strategy' => 'sync.ui.default_strategy',
            'transport.timeout' => 'sync.transport.timeout',
            'transport.retry_times' => 'sync.transport.retry_times',
            'transport.retry_sleep_ms' => 'sync.transport.retry_sleep_ms',
            'transport.verify_ssl' => 'sync.transport.verify_ssl',
            'receiver.enabled' => 'sync.receiver.enabled',
            'receiver.api_key' => 'sync.receiver.api_key',
            'receiver.route_prefix' => 'sync.receiver.route_prefix',
            'notifications.email' => 'sync.advanced.notifications.email',
            'notifications.webhook' => 'sync.advanced.notifications.webhook',
        ];

        foreach (SyncSetting::query()->get() as $setting) {
            $path = $map[$setting->key] ?? null;

            if (! $path) {
                continue;
            }

            $value = $setting->value;

            if (is_array($value) && array_key_exists('value', $value) && count($value) === 1) {
                $value = $value['value'];
            }

            config()->set($path, $value);
        }
    }
}
