@extends('sync-manager::layout')

@section('content')
    <div
        x-data="deploycarDashboard({
            mode: 'production',
            pollMs: {{ (int) config('sync.ui.poll_interval_ms', 1500) }},
            routes: {
                operationStart: '{{ route('sync-manager.operations.start') }}',
                operationShow: '{{ route('sync-manager.operations.show', ['operationId' => '__OPERATION__']) }}',
                targetSave: '{{ route('sync-manager.configuration.targets.save') }}',
                targetDelete: '{{ route('sync-manager.configuration.targets.delete', ['targetId' => '__TARGET__']) }}',
                settingsSave: '{{ route('sync-manager.configuration.settings.save') }}',
            },
            initial: {
                defaultStrategy: @js($settings['default_strategy'] ?? 'preview'),
                versions: @js($versions->values()),
                operations: @js(collect($operations)->values()),
                activeOperation: @js($activeOperation),
                currentState: @js($currentState),
                managedTargets: @js(collect($managedTargets)->values()),
                settings: @js($settings),
            }
        })"
        x-cloak
    >
        @if ($migrationWarning)
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4">
                <div class="flex items-start gap-3">
                    <svg class="mt-0.5 size-4 shrink-0 text-amber-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                    <div class="flex-1">
                        <h3 class="text-sm font-medium text-amber-800">Migration required</h3>
                        <p class="mt-1 text-xs text-amber-700">{{ $migrationWarning }}</p>
                    </div>
                    <code class="rounded bg-amber-100 px-2 py-1 text-xs font-mono text-amber-800">php artisan migrate</code>
                </div>
            </div>
        @endif

        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-sky-100">
                    <svg class="size-5 text-sky-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" /></svg>
                </div>
                <div>
                    <h2 class="text-lg font-semibold tracking-tight">Production</h2>
                    <p class="text-sm text-muted-foreground">Receiver health & rollback</p>
                </div>
            </div>
            <button class="inline-flex items-center justify-center rounded-md border border-input bg-transparent px-4 py-2 text-sm font-medium text-destructive shadow-sm transition-colors hover:bg-red-50 disabled:pointer-events-none disabled:opacity-50" :disabled="loadingAction" @click="openConfirm('undo', 'Undo last sync')">
                <svg class="mr-2 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" /></svg>
                Undo last sync
            </button>
        </div>

        <div class="mb-6">
            <div class="inline-flex items-center rounded-lg bg-muted p-1">
                <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium transition-all" :class="tab === 'overview' ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'" @click="tab = 'overview'">Overview</button>
                <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium transition-all" :class="tab === 'settings' ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'" @click="tab = 'settings'">Settings</button>
                <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium transition-all" :class="tab === 'activity' ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'" @click="tab = 'activity'">Activity</button>
            </div>
        </div>

        <section x-show="tab === 'overview'" class="grid gap-4 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                <div class="rounded-lg border bg-card shadow-sm">
                    <div class="flex items-center justify-between border-b px-4 py-3">
                        <h3 class="text-sm font-medium">Receiver</h3>
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ config('sync.receiver.enabled', true) ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">
                            {{ config('sync.receiver.enabled', true) ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>
                    <div class="p-4">
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="rounded-md border bg-muted/50 p-3">
                                <p class="text-xs text-muted-foreground">Route prefix</p>
                                <p class="mt-1 font-mono text-sm font-semibold">{{ config('sync.receiver.route_prefix', 'sync') }}</p>
                            </div>
                            <div class="rounded-md border bg-muted/50 p-3">
                                <p class="text-xs text-muted-foreground">Tracked files</p>
                                <p class="mt-1 text-2xl font-bold">{{ count($currentState) }}</p>
                            </div>
                            <div class="rounded-md border bg-muted/50 p-3">
                                <p class="text-xs text-muted-foreground">Versions</p>
                                <p class="mt-1 text-2xl font-bold">{{ $versions->count() }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border bg-card shadow-sm">
                    <div class="border-b px-4 py-3">
                        <h3 class="text-sm font-medium">Tracked State</h3>
                    </div>
                    <div class="overflow-x-auto">
                        @if (count($currentState))
                            <table class="w-full text-sm">
                                <thead>
                                <tr class="border-b bg-muted/50">
                                    <th class="px-4 py-2 text-left text-xs font-medium text-muted-foreground">Path</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-muted-foreground">Hash</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-muted-foreground">Size</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach (collect($currentState)->take(10) as $path => $file)
                                    <tr class="border-b last:border-b-0">
                                        <td class="max-w-[200px] truncate px-4 py-2.5 font-medium">{{ $path }}</td>
                                        <td class="px-4 py-2.5"><code class="rounded bg-muted px-1.5 py-0.5 text-xs font-mono">{{ $file['hash'] ?? '-' }}</code></td>
                                        <td class="px-4 py-2.5 text-muted-foreground">{{ $file['size'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="flex flex-col items-center justify-center py-12 text-center">
                                <svg class="mb-3 size-8 text-muted-foreground/50" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                                <p class="text-sm text-muted-foreground">No tracked state yet</p>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="rounded-lg border bg-card shadow-sm">
                    <div class="border-b px-4 py-3">
                        <h3 class="text-sm font-medium">Recent Versions</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                            <tr class="border-b bg-muted/50">
                                <th class="px-4 py-2 text-left text-xs font-medium text-muted-foreground">Version</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-muted-foreground">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-muted-foreground">Direction</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-muted-foreground"></th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse ($versions as $version)
                                <tr class="border-b last:border-b-0">
                                    <td class="px-4 py-2.5 font-mono text-xs">{{ $version->version_id }}</td>
                                    <td class="px-4 py-2.5">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                            @if($version->status === 'success') bg-emerald-50 text-emerald-700
                                            @elseif($version->status === 'failed') bg-red-50 text-red-700
                                            @else bg-amber-50 text-amber-700 @endif">
                                            {{ $version->status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-muted-foreground">{{ $version->direction }}</td>
                                    <td class="px-4 py-2.5">
                                        <button type="button" class="inline-flex items-center rounded-md border border-input px-2.5 py-1 text-xs font-medium shadow-sm transition-colors hover:bg-accent" @click="openConfirm('rollback', 'Rollback to {{ $version->version_id }}', { version_id: '{{ $version->version_id }}' })">
                                            Rollback
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-sm text-muted-foreground">No versions recorded</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <aside class="space-y-4">
                <div class="rounded-lg border bg-card shadow-sm">
                    <div class="flex items-center justify-between border-b px-4 py-3">
                        <h3 class="text-sm font-medium">Current Operation</h3>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                              :class="{
                                  'bg-emerald-50 text-emerald-700': activeOperation?.status === 'success',
                                  'bg-red-50 text-red-700': activeOperation?.status === 'failed',
                                  'bg-amber-50 text-amber-700': activeOperation?.status === 'running' || activeOperation?.status === 'queued',
                                  'bg-muted text-muted-foreground': !activeOperation
                              }"
                              x-text="activeOperation?.status || 'idle'"></span>
                    </div>
                    <div class="p-4 space-y-4">
                        <p class="text-xs text-muted-foreground" x-text="activeOperation?.message || 'No active operation'"></p>
                        <div class="h-1.5 overflow-hidden rounded-full bg-muted" x-show="activeOperation">
                            <div class="h-full bg-primary transition-all duration-300" :style="`width: ${activeOperation?.progress || 0}%`"></div>
                        </div>
                    </div>
                </div>
            </aside>
        </section>

        <section x-show="tab === 'settings'">
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-lg border bg-card shadow-sm">
                    <div class="border-b px-4 py-3">
                        <h3 class="text-sm font-medium">Receiver</h3>
                    </div>
                    <div class="p-4 space-y-4">
                        <div class="space-y-2">
                            <label class="text-sm font-medium leading-none">Route prefix</label>
                            <input x-model="settingsForm.receiver.route_prefix" placeholder="sync" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium leading-none">API key</label>
                            <input x-model="settingsForm.receiver.api_key" type="password" placeholder="sync-api-key" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                        </div>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" x-model="settingsForm.receiver.enabled" class="size-4 rounded border-input text-primary focus:ring-ring">
                            <span class="text-sm text-muted-foreground">Receiver enabled</span>
                        </label>
                    </div>
                </div>

                <div class="rounded-lg border bg-card shadow-sm">
                    <div class="border-b px-4 py-3">
                        <h3 class="text-sm font-medium">Transport</h3>
                    </div>
                    <div class="p-4 space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-sm font-medium leading-none">Timeout (s)</label>
                                <input type="number" min="1" x-model.number="settingsForm.transport.timeout" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium leading-none">Retries</label>
                                <input type="number" min="1" x-model.number="settingsForm.transport.retry_times" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-sm font-medium leading-none">Retry sleep (ms)</label>
                                <input type="number" min="0" x-model.number="settingsForm.transport.retry_sleep_ms" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                            </div>
                            <div class="flex items-end pb-1">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" x-model="settingsForm.transport.verify_ssl" class="size-4 rounded border-input text-primary focus:ring-ring">
                                    <span class="text-sm text-muted-foreground">Verify SSL</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border bg-card shadow-sm lg:col-span-2">
                    <div class="border-b px-4 py-3">
                        <h3 class="text-sm font-medium">Notifications</h3>
                    </div>
                    <div class="p-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-sm font-medium leading-none">Email</label>
                                <input x-model="settingsForm.notifications.email" placeholder="ops@example.com" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium leading-none">Webhook URL</label>
                                <input x-model="settingsForm.notifications.webhook" placeholder="https://hooks.example.com/..." class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                            </div>
                        </div>
                        <div class="mt-4">
                            <button class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm transition-colors hover:bg-primary/90" @click="saveSettings">Save settings</button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section x-show="tab === 'activity'">
            <div class="rounded-lg border bg-card shadow-sm">
                <div class="border-b px-4 py-3">
                    <h3 class="text-sm font-medium">Operations</h3>
                </div>
                <div class="divide-y">
                    <template x-for="operation in operations" :key="operation.operation_id">
                        <div class="flex items-center justify-between px-4 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium" x-text="operation.type"></p>
                                <p class="truncate text-xs text-muted-foreground" x-text="operation.message || 'Waiting...'"></p>
                            </div>
                            <div class="ml-4 flex shrink-0 items-center gap-3">
                                <div class="w-20 h-1.5 overflow-hidden rounded-full bg-muted">
                                    <div class="h-full bg-primary transition-all duration-300" :style="`width: ${operation.progress || 0}%`"></div>
                                </div>
                                <span class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                      :class="{
                                          'bg-emerald-50 text-emerald-700': operation.status === 'success',
                                          'bg-red-50 text-red-700': operation.status === 'failed',
                                          'bg-amber-50 text-amber-700': operation.status === 'running' || operation.status === 'queued',
                                          'bg-muted text-muted-foreground': !operation.status
                                      }"
                                      x-text="operation.status"></span>
                            </div>
                        </div>
                    </template>

                    <div class="flex flex-col items-center justify-center py-12 text-center" x-show="operations.length === 0">
                        <svg class="mb-3 size-8 text-muted-foreground/50" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" /></svg>
                        <p class="text-sm text-muted-foreground">No operations yet</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="fixed right-4 top-4 z-50 w-80 space-y-2">
            <template x-for="toast in toasts" :key="toast.id">
                <div class="rounded-lg border bg-card p-3 shadow-lg"
                     :class="{
                         'border-emerald-200': toast.tone === 'success',
                         'border-red-200': toast.tone === 'danger',
                         'border-amber-200': toast.tone === 'warning'
                     }">
                    <p class="text-sm font-medium" x-text="toast.title"></p>
                    <p class="mt-0.5 text-xs text-muted-foreground" x-text="toast.body"></p>
                </div>
            </template>
        </div>

        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" x-show="showConfirm">
            <div class="w-full max-w-md rounded-lg border bg-card p-5 shadow-lg">
                <h3 class="text-base font-semibold">Confirm action</h3>
                <p class="mt-1 text-sm text-muted-foreground" x-text="confirmPayload?.label ? `${confirmPayload.label} will run in background.` : 'Continue?'"></p>
                <div class="mt-4 flex justify-end gap-2">
                    <button class="inline-flex items-center justify-center rounded-md border border-input bg-transparent px-4 py-2 text-sm font-medium shadow-sm transition-colors hover:bg-accent" @click="showConfirm = false">Cancel</button>
                    <button class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm transition-colors hover:bg-primary/90" @click="confirmAction">Start</button>
                </div>
            </div>
        </div>
    </div>
@endsection
