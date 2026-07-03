<?php

namespace DeployCar\LaravelSyncManager\Controllers;

use DeployCar\LaravelSyncManager\Contracts\ApplyServiceInterface;
use DeployCar\LaravelSyncManager\Contracts\ObjectStoreInterface;
use DeployCar\LaravelSyncManager\Contracts\StateRepositoryInterface;
use DeployCar\LaravelSyncManager\Services\FileScanner;
use DeployCar\LaravelSyncManager\Services\PathSecurity;
use DeployCar\LaravelSyncManager\Services\ProtocolSecurity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReceiverController extends Controller
{
    public function __construct(
        protected ObjectStoreInterface $objectStore,
        protected StateRepositoryInterface $stateRepository,
        protected ApplyServiceInterface $applyService,
        protected ProtocolSecurity $protocolSecurity,
        protected PathSecurity $pathSecurity
    ) {
    }

    public function state(FileScanner $scanner, Request $request): JsonResponse
    {
        $this->protocolSecurity->verifyRequest($request, (string) config('sync.receiver.api_key'), '');
        $targetName = (string) config('sync.target.name');
        $tracked = $this->stateRepository->forTarget($targetName);

        if ($tracked === []) {
            $tracked = collect($scanner->scan(base_path()))->mapWithKeys(static fn (array $file) => [
                $file['path'] => [
                    'hash' => $file['hash'],
                    'size' => $file['size'],
                    'modified_at' => $file['modified_at'],
                ],
            ])->all();
        }

        return new JsonResponse([
            'status' => 'success',
            'target' => $targetName,
            'manifest_id' => $this->stateRepository->latestManifestId($targetName),
            'files' => $tracked,
        ]);
    }

    public function checkObjects(Request $request): JsonResponse
    {
        $body = $request->getContent();
        $this->protocolSecurity->verifyRequest($request, (string) config('sync.receiver.api_key'), $body);

        $data = $request->validate([
            'hashes' => ['required', 'array'],
            'hashes.*' => ['required', 'string'],
        ]);

        $missing = array_values(array_filter($data['hashes'], fn (string $hash) => ! $this->objectStore->has($hash)));

        return new JsonResponse([
            'status' => 'success',
            'missing' => $missing,
        ]);
    }

    public function uploadObject(Request $request, string $hash): JsonResponse
    {
        $maxBytes = (int) config('sync.objects.max_size_bytes', 50 * 1024 * 1024);
        $contentLength = (int) $request->header('Content-Length', '0');
        if ($contentLength > $maxBytes) {
            return new JsonResponse([
                'status' => 'failed',
                'message' => 'Upload exceeds the maximum allowed object size.',
            ], 413);
        }

        $body = (string) $request->getContent();
        $this->protocolSecurity->verifyRequest($request, (string) config('sync.receiver.api_key'), $body);
        $stored = $this->objectStore->storeContents($body, $hash);

        return new JsonResponse([
            'status' => 'success',
            'hash' => $stored['hash'],
            'size' => $stored['size'],
        ]);
    }

    public function downloadObject(Request $request, string $hash): BinaryFileResponse
    {
        $this->protocolSecurity->verifyRequest($request, (string) config('sync.receiver.api_key'), '');

        abort_unless($this->objectStore->has($hash), 404);

        return response()->file($this->objectStore->path($hash), [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => "attachment; filename=\"{$hash}.blob\"",
        ]);
    }

    public function commit(Request $request): JsonResponse
    {
        $body = $request->getContent();
        $this->protocolSecurity->verifyRequest($request, (string) config('sync.receiver.api_key'), $body);

        $manifest = $request->validate([
            'manifest_id' => ['required', 'string'],
            'version_id' => ['required', 'string'],
            'timestamp' => ['required', 'string'],
            'source_app' => ['nullable', 'string'],
            'target_name' => ['required', 'string'],
            'parent_manifest_id' => ['nullable', 'string'],
            'summary' => ['required', 'array'],
            'expected_target' => ['present', 'array'],
            'files' => ['required', 'array'],
            'files.*.path' => ['required', 'string'],
            'files.*.hash' => ['required', 'string'],
            'files.*.size' => ['nullable', 'integer'],
            'files.*.modified_at' => ['nullable', 'string'],
            'files.*.status' => ['required', 'string'],
        ]);

        foreach ($manifest['files'] as $file) {
            $this->pathSecurity->assertSafe((string) $file['path']);
        }

        $manifest['signature'] = (string) $request->header('X-Sync-Signature');
        $manifest['target_name'] = (string) config('sync.target.name');
        $response = $this->applyService->commit($manifest);

        return new JsonResponse(array_merge($response, [
            'manifest_signature' => $manifest['signature'],
        ]));
    }
}
