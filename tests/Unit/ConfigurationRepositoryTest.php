<?php

namespace DeployCar\LaravelSyncManager\Tests\Unit;

use DeployCar\LaravelSyncManager\Services\ConfigurationRepository;
use DeployCar\LaravelSyncManager\Services\RuntimeConfigurationLoader;
use DeployCar\LaravelSyncManager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

class ConfigurationRepositoryTest extends TestCase
{
    use RefreshDatabase;

    // ---------- dashboard settings ----------

    public function test_dashboard_settings_does_not_include_raw_api_key(): void
    {
        config()->set('sync.receiver.api_key', 'super-secret');

        $settings = app(ConfigurationRepository::class)->dashboardSettings();

        $this->assertArrayNotHasKey('api_key', $settings['receiver']);
        $this->assertTrue($settings['receiver']['api_key_configured']);
    }

    public function test_dashboard_settings_indicates_when_key_not_configured(): void
    {
        config()->set('sync.receiver.api_key', '');

        $settings = app(ConfigurationRepository::class)->dashboardSettings();

        $this->assertFalse($settings['receiver']['api_key_configured']);
    }

    public function test_dashboard_settings_reports_change_me_as_not_configured(): void
    {
        config()->set('sync.receiver.api_key', 'change-me');

        $settings = app(ConfigurationRepository::class)->dashboardSettings();

        $this->assertFalse($settings['receiver']['api_key_configured']);
    }

    public function test_dashboard_settings_uses_true_for_verify_ssl(): void
    {
        $settings = app(ConfigurationRepository::class)->dashboardSettings();

        $this->assertTrue($settings['transport']['verify_ssl']);
    }

    // ---------- validation rules ----------

    public function test_webhook_accepts_https(): void
    {
        $validator = Validator::make(
            ['webhook' => 'https://hooks.example.com/push'],
            ['webhook' => ['nullable', 'url', 'regex:/^https:\/\//i']]
        );

        $this->assertTrue($validator->passes());
    }

    public function test_webhook_rejects_http(): void
    {
        $validator = Validator::make(
            ['webhook' => 'http://hooks.example.com/push'],
            ['webhook' => ['nullable', 'url', 'regex:/^https:\/\//i']]
        );

        $this->assertFalse($validator->passes());
    }

    public function test_webhook_allows_empty(): void
    {
        $validator = Validator::make(
            ['webhook' => ''],
            ['webhook' => ['nullable', 'url', 'regex:/^https:\/\//i']]
        );

        $this->assertTrue($validator->passes());
    }

    public function test_webhook_rejects_non_url(): void
    {
        $validator = Validator::make(
            ['webhook' => 'not-a-url'],
            ['webhook' => ['nullable', 'url', 'regex:/^https:\/\//i']]
        );

        $this->assertFalse($validator->passes());
    }

    public function test_email_accepts_valid(): void
    {
        $validator = Validator::make(
            ['email' => 'ops@example.com'],
            ['email' => ['nullable', 'email']]
        );

        $this->assertTrue($validator->passes());
    }

    public function test_email_rejects_invalid(): void
    {
        $validator = Validator::make(
            ['email' => 'not-an-email'],
            ['email' => ['nullable', 'email']]
        );

        $this->assertFalse($validator->passes());
    }

    public function test_email_allows_empty(): void
    {
        $validator = Validator::make(
            ['email' => ''],
            ['email' => ['nullable', 'email']]
        );

        $this->assertTrue($validator->passes());
    }

    // ---------- save / load ----------

    public function test_save_and_load_settings_persists_transport_values(): void
    {
        $repo = app(ConfigurationRepository::class);
        $repo->saveSettings([
            'default_strategy' => 'production-first',
            'transport' => [
                'timeout' => 60,
                'retry_times' => 5,
                'retry_sleep_ms' => 1000,
                'verify_ssl' => true,
            ],
            'receiver' => [
                'enabled' => true,
                'route_prefix' => 'sync-api',
                'api_key' => '',
            ],
            'notifications' => [
                'email' => '',
                'webhook' => '',
            ],
        ]);

        // Reload the runtime config from the DB so config() reflects the changes.
        app(RuntimeConfigurationLoader::class)->load();

        $this->assertSame('production-first', config('sync.ui.default_strategy'));
        $this->assertSame(60, config('sync.transport.timeout'));
        $this->assertSame(5, config('sync.transport.retry_times'));
        $this->assertTrue(config('sync.transport.verify_ssl'));
    }

    public function test_save_settings_does_not_overwrite_api_key_when_empty(): void
    {
        config()->set('sync.receiver.api_key', 'existing-key');

        $repo = app(ConfigurationRepository::class);
        $repo->saveSettings([
            'default_strategy' => 'preview',
            'transport' => ['timeout' => 30, 'retry_times' => 3, 'retry_sleep_ms' => 500, 'verify_ssl' => true],
            'receiver' => ['enabled' => true, 'route_prefix' => 'sync', 'api_key' => ''],
            'notifications' => ['email' => '', 'webhook' => ''],
        ]);

        app(RuntimeConfigurationLoader::class)->load();
        $this->assertSame('existing-key', config('sync.receiver.api_key'));
    }
}
