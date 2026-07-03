# Laravel Sync Manager

A Laravel package for incremental local-to-production sync with tracked state, history, and rollback support.

## Installation

You can install the package via composer:

```bash
composer require deploycar/laravel-sync-manager
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=sync-manager-config
```

Configure your environment variables in `.env`:

```env
SYNC_MANAGER_API_KEY=your-secure-random-string
SYNC_MANAGER_TARGET_URL=https://production.yourdomain.com
```

## Security Overview (Production Environments)

When running `laravel-sync-manager` in a production environment, strict security boundaries are automatically enforced to prevent accidental overwrites or malicious usage.

### 1. UI Authorization Gate
The Web UI (`/sync/local`, `/sync/production`) is accessible without authentication in the `local` environment. However, **in production, the UI is disabled by default** and protected by the `viewSyncManager` Gate.

To grant access to specific administrators, define the gate in your `App\Providers\AuthServiceProvider`:

```php
use Illuminate\Support\Facades\Gate;

public function boot()
{
    Gate::define('viewSyncManager', function ($user) {
        // Example: Only allow admins
        return in_array($user->email, [
            'admin@yourdomain.com',
        ]);
    });
}
```

### 2. API Token Hardening
The receiver API endpoints use a token for verifying requests. The default `SYNC_MANAGER_API_KEY` is `'change-me'`. 

**In production, the package will instantly throw a 403 Forbidden error if the default API key is used.** You must define a secure key in your environment.

### 3. API Rate Limiting
All receiver API endpoints (`/sync/objects/*`, `/sync/commit`) are protected by the `throttle:60,1` middleware to mitigate brute force attacks and denial-of-service attempts.

### 4. Destructive CLI Commands
When running CLI commands that mutate state or files on a production environment (such as `sync:run`, `sync:pull`, `sync:rollback`, `sync:restore-local`), the application will enforce a strict manual confirmation.

You will be prompted with:
`Please type "I know what I am doing" to confirm running this destructive command`

If you are running these commands in a CI/CD pipeline or non-interactive deployment script, you must explicitly bypass this using the `--force` flag:

```bash
php artisan sync:run --force
```

## Available Commands

- `php artisan sync:run` / `sync:send` - Sync local changes to the target environment.
- `php artisan sync:pull` - Pull remote files to the local environment.
- `php artisan sync:rollback` - Revert to a previous tracked sync state.
- `php artisan sync:restore-local` - Restore the local files from a previously created backup.
- `php artisan sync:scan` - Scan local files and generate a hash manifest.
# laravel-sync-manager
