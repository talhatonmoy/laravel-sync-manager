<?php

namespace DeployCar\LaravelSyncManager\Controllers;

use DeployCar\LaravelSyncManager\Services\ConfigurationRepository;
use DeployCar\LaravelSyncManager\Services\IgnoreManager;
use DeployCar\LaravelSyncManager\Services\RuntimeConfigurationLoader;
use DeployCar\LaravelSyncManager\Services\SchemaReadiness;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ConfigurationController extends Controller
{
    public function saveTarget(
        Request $request,
        ConfigurationRepository $repository,
        RuntimeConfigurationLoader $loader,
        SchemaReadiness $schemaReadiness
    ): JsonResponse
    {
        if (! $schemaReadiness->hasTargets()) {
            return new JsonResponse([
                'status' => 'failed',
                'message' => $schemaReadiness->migrationMessage(),
            ], 409);
        }

        $data = $request->validate([
            'name' => ['required', 'string'],
            'url' => ['required', 'url'],
            'api_key' => ['nullable', 'string'],
            'source_app_id' => ['nullable', 'string'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $target = $repository->saveTarget($data);
        $loader->load();

        return new JsonResponse([
            'status' => 'success',
            'target' => $target,
        ]);
    }

    public function deleteTarget(
        int $targetId,
        ConfigurationRepository $repository,
        RuntimeConfigurationLoader $loader,
        SchemaReadiness $schemaReadiness
    ): JsonResponse
    {
        if (! $schemaReadiness->hasTargets()) {
            return new JsonResponse([
                'status' => 'failed',
                'message' => $schemaReadiness->migrationMessage(),
            ], 409);
        }

        $repository->deleteTarget($targetId);
        $loader->load();

        return new JsonResponse([
            'status' => 'success',
        ]);
    }

    public function saveSettings(
        Request $request,
        ConfigurationRepository $repository,
        RuntimeConfigurationLoader $loader,
        SchemaReadiness $schemaReadiness
    ): JsonResponse
    {
        if (! $schemaReadiness->hasSettings()) {
            return new JsonResponse([
                'status' => 'failed',
                'message' => $schemaReadiness->migrationMessage(),
            ], 409);
        }

        $data = $request->validate([
            'default_strategy' => ['required', 'string'],
            'transport.timeout' => ['required', 'integer', 'min:1'],
            'transport.retry_times' => ['required', 'integer', 'min:1'],
            'transport.retry_sleep_ms' => ['required', 'integer', 'min:0'],
            'transport.verify_ssl' => ['nullable', 'boolean'],
            'receiver.enabled' => ['nullable', 'boolean'],
            'receiver.route_prefix' => ['required', 'string'],
            'receiver.api_key' => ['nullable', 'string'],
            'notifications.email' => ['nullable', 'email'],
            'notifications.webhook' => ['nullable', 'url', 'regex:/^https:\/\//i'],
        ]);

        $repository->saveSettings([
            'default_strategy' => $data['default_strategy'],
            'transport' => [
                'timeout' => $data['transport']['timeout'],
                'retry_times' => $data['transport']['retry_times'],
                'retry_sleep_ms' => $data['transport']['retry_sleep_ms'],
                'verify_ssl' => (bool) ($data['transport']['verify_ssl'] ?? false),
            ],
            'receiver' => [
                'enabled' => (bool) ($data['receiver']['enabled'] ?? false),
                'route_prefix' => $data['receiver']['route_prefix'],
                'api_key' => $data['receiver']['api_key'] ?? '',
            ],
            'notifications' => [
                'email' => $data['notifications']['email'] ?? '',
                'webhook' => $data['notifications']['webhook'] ?? '',
            ],
        ]);
        $loader->load();

        return new JsonResponse([
            'status' => 'success',
            'settings' => $repository->dashboardSettings(),
        ]);
    }

    public function getIgnorePatterns(IgnoreManager $ignoreManager): JsonResponse
    {
        $defaults = config('sync.ignore.defaults', []);
        $all = $ignoreManager->patterns();
        $custom = array_values(array_diff($all, $defaults));

        return new JsonResponse([
            'defaults' => $defaults,
            'custom' => $custom,
            'all' => $all,
        ]);
    }

    public function addIgnorePattern(Request $request, Filesystem $files): JsonResponse
    {
        $data = $request->validate([
            'pattern' => ['required', 'string', 'max:255'],
        ]);

        $pattern = trim($data['pattern']);

        if ($pattern === '' || str_starts_with($pattern, '#')) {
            return new JsonResponse([
                'status' => 'failed',
                'message' => 'Invalid pattern.',
            ], 422);
        }

        $ignoreFile = rtrim((string) config('sync.source_path'), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .config('sync.ignore.file_name', '.syncignore');

        $existing = [];
        if ($files->exists($ignoreFile)) {
            $existing = preg_split('/\r\n|\r|\n/', $files->get($ignoreFile)) ?: [];
            $existing = array_map('trim', $existing);
        }

        if (in_array($pattern, $existing, true)) {
            return new JsonResponse([
                'status' => 'failed',
                'message' => 'Pattern already exists.',
            ], 422);
        }

        $existing[] = $pattern;
        $files->put($ignoreFile, implode(PHP_EOL, array_filter($existing)) . PHP_EOL);

        return new JsonResponse([
            'status' => 'success',
            'pattern' => $pattern,
        ]);
    }

    public function removeIgnorePattern(Request $request, Filesystem $files): JsonResponse
    {
        $data = $request->validate([
            'pattern' => ['required', 'string', 'max:255'],
        ]);

        $pattern = trim($data['pattern']);
        $defaults = config('sync.ignore.defaults', []);

        if (in_array($pattern, $defaults, true)) {
            return new JsonResponse([
                'status' => 'failed',
                'message' => 'Cannot remove default patterns.',
            ], 422);
        }

        $ignoreFile = rtrim((string) config('sync.source_path'), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .config('sync.ignore.file_name', '.syncignore');

        if (! $files->exists($ignoreFile)) {
            return new JsonResponse([
                'status' => 'failed',
                'message' => 'No custom patterns found.',
            ], 404);
        }

        $lines = preg_split('/\r\n|\r|\n/', $files->get($ignoreFile)) ?: [];
        $lines = array_map('trim', $lines);
        $filtered = array_values(array_filter($lines, fn ($line) => $line !== $pattern));

        if (count($filtered) === 0) {
            $files->delete($ignoreFile);
        } else {
            $files->put($ignoreFile, implode(PHP_EOL, array_filter($filtered)) . PHP_EOL);
        }

        return new JsonResponse([
            'status' => 'success',
        ]);
    }
}
