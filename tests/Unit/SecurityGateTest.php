<?php

namespace DeployCar\LaravelSyncManager\Tests\Unit;

use DeployCar\LaravelSyncManager\Contracts\SecurityGateInterface;
use DeployCar\LaravelSyncManager\Exceptions\SecurityViolationException;
use DeployCar\LaravelSyncManager\Tests\TestCase;

class SecurityGateTest extends TestCase
{
    private SecurityGateInterface $gate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gate = app(SecurityGateInterface::class);
    }

    // ---------- dangerous patterns ----------

    public function test_rejects_env_file(): void
    {
        $this->expectException(SecurityViolationException::class);
        $this->gate->assertSafe('.env');
    }

    public function test_rejects_env_in_subdirectory(): void
    {
        $this->expectException(SecurityViolationException::class);
        $this->gate->assertSafe('app/.env.backup');
    }

    public function test_rejects_key_file(): void
    {
        $this->expectException(SecurityViolationException::class);
        $this->gate->assertSafe('storage/private.key');
    }

    public function test_rejects_artisan(): void
    {
        $this->expectException(SecurityViolationException::class);
        $this->gate->assertSafe('artisan');
    }

    public function test_allows_safe_path(): void
    {
        // Should not throw
        $this->gate->assertSafe('app/Models/User.php');
        $this->gate->assertSafe('resources/views/dashboard.blade.php');
        $this->gate->assertSafe('routes/web.php');

        // No assertion needed — no exception means pass
        $this->assertTrue(true);
    }

    // ---------- strict mode off ----------

    public function test_does_not_check_when_strict_mode_is_off(): void
    {
        config()->set('sync.security.strict_mode', false);
        $this->gate->assertSafe('.env');
        $this->gate->assertSafe('storage/database.sqlite');
        $this->assertTrue(true);
    }

    // ---------- subtree allow-list ----------

    public function test_rejects_outside_allow_list(): void
    {
        config()->set('sync.security.writable_subtrees', ['app/', 'resources/views/']);

        $this->expectException(SecurityViolationException::class);
        $this->expectExceptionMessage('outside the allowed writable subtrees');

        $this->gate->assertSafe('vendor/some-package/file.php');
    }

    public function test_accepts_path_under_allowed_subtree(): void
    {
        config()->set('sync.security.writable_subtrees', ['app/', 'resources/views/']);

        // Should not throw
        $this->gate->assertSafe('app/Models/User.php');
        $this->gate->assertSafe('resources/views/welcome.blade.php');
        $this->assertTrue(true);
    }

    public function test_allows_everything_when_subtree_list_empty(): void
    {
        config()->set('sync.security.writable_subtrees', []);

        $this->gate->assertSafe('vendor/any/file.php');
        $this->gate->assertSafe('../some-outside-path.php');
        $this->assertTrue(true);
    }
}
