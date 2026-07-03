<?php

namespace DeployCar\LaravelSyncManager\Services;

use DeployCar\LaravelSyncManager\Models\SyncSetting;
use DeployCar\LaravelSyncManager\Models\SyncTarget;
use Illuminate\Support\Facades\Schema;

class ConfigurationRepository
{
    public function dashboardSettings(): array
    {
        $rawKey = (string) config('sync.receiver.api_key', '');

        return [
            'default_strategy' => (string) config('sync.ui.default_strategy', 'preview'),
            'transport' => [
                'timeout' => (int) config('sync.transport.timeout', 30),
                'retry_times' => (int) config('sync.transport.retry_times', 3),
                'retry_sleep_ms' => (int) config('sync.transport.retry_sleep_ms', 500),
                'verify_ssl' => (bool) config('sync.transport.verify_ssl', true),
            ],
            'receiver' => [
                'enabled' => (bool) config('sync.receiver.enabled', true),
                'route_prefix' => (string) config('sync.receiver.route_prefix', 'sync'),
                'api_key_configured' => $rawKey !== '' && $rawKey !== 'change-me',
            ],
            'notifications' => [
                'email' => (string) config('sync.advanced.notifications.email', ''),
                'webhook' => (string) config('sync.advanced.notifications.webhook', ''),
            ],
        ];
    }

    public function saveSettings(array $settings): void
    {
        if (! Schema::hasTable('sync_settings')) {
            return;
        }

        $flat = [
            'default_strategy' => $settings['default_strategy'] ?? 'preview',
            'transport.timeout' => (int) ($settings['transport']['timeout'] ?? 30),
            'transport.retry_times' => (int) ($settings['transport']['retry_times'] ?? 3),
            'transport.retry_sleep_ms' => (int) ($settings['transport']['retry_sleep_ms'] ?? 500),
            'transport.verify_ssl' => (bool) ($settings['transport']['verify_ssl'] ?? true),
            'receiver.enabled' => (bool) ($settings['receiver']['enabled'] ?? true),
            'receiver.route_prefix' => (string) ($settings['receiver']['route_prefix'] ?? 'sync'),
            'notifications.email' => (string) ($settings['notifications']['email'] ?? ''),
            'notifications.webhook' => (string) ($settings['notifications']['webhook'] ?? ''),
        ];

        // Send/receive API key as write-only: only persist when a non-empty
        // value is provided, so the dashboard never stores the secret in
        // client-side state.
        $incomingKey = $settings['receiver']['api_key'] ?? '';
        if ($incomingKey !== '') {
            $flat['receiver.api_key'] = $incomingKey;
        }

        foreach ($flat as $key => $value) {
            SyncSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => ['value' => $value]]
            );
        }
    }

    public function targets(): array
    {
        if (! Schema::hasTable('sync_targets')) {
            return config('sync.targets', []);
        }

        return SyncTarget::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->all();
    }

    public function saveTarget(array $attributes): SyncTarget
    {
        $data = [
            'url' => rtrim((string) $attributes['url'], '/'),
            'source_app_id' => $attributes['source_app_id'] ?: null,
            'is_default' => (bool) ($attributes['is_default'] ?? false),
        ];

        // Write-only: only update the key when a new value is provided.
        if (! empty($attributes['api_key'])) {
            $data['api_key'] = $attributes['api_key'];
        }

        $target = SyncTarget::query()->updateOrCreate(
            ['name' => $attributes['name']],
            $data
        );

        if ($target->is_default) {
            SyncTarget::query()->where('id', '!=', $target->id)->update(['is_default' => false]);
        }

        return $target->refresh();
    }

    public function deleteTarget(int $targetId): void
    {
        if (! Schema::hasTable('sync_targets')) {
            return;
        }

        $target = SyncTarget::query()->findOrFail($targetId);
        $wasDefault = $target->is_default;
        $target->delete();

        if ($wasDefault) {
            $next = SyncTarget::query()->orderBy('name')->first();
            $next?->forceFill(['is_default' => true])->save();
        }
    }
}
