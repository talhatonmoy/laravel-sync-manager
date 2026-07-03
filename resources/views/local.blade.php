@extends('sync-manager::layout')

@section('content')
    <div
        x-data="deploycarDashboard({
            mode: 'local',
            pollMs: {{ (int) config('sync.ui.poll_interval_ms', 1500) }},
            routes: {
                operationStart: '{{ route('sync-manager.operations.start') }}',
                operationShow: '{{ route('sync-manager.operations.show', ['operationId' => '__OPERATION__']) }}',
                targetSave: '{{ route('sync-manager.configuration.targets.save') }}',
                targetDelete: '{{ route('sync-manager.configuration.targets.delete', ['targetId' => '__TARGET__']) }}',
                settingsSave: '{{ route('sync-manager.configuration.settings.save') }}',
                ignoreIndex: '{{ route('sync-manager.configuration.ignore.index') }}',
                ignoreStore: '{{ route('sync-manager.configuration.ignore.store') }}',
                ignoreDelete: '{{ route('sync-manager.configuration.ignore.delete') }}',
            },
            initial: {
                defaultStrategy: @js($defaultStrategy),
                selectedTarget: @js($targets[0]['name'] ?? null),
                versions: @js($versions->values()),
                operations: @js(collect($operations)->values()),
                activeOperation: @js($activeOperation),
                summary: @js($summary),
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
                <div class="flex size-10 items-center justify-center rounded-lg bg-emerald-100">
                    <svg class="size-5 text-emerald-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.999 0-5.71-1.24-7.643-3.238m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5a17.92 17.92 0 0 1-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" /></svg>
                </div>
                <div>
                    <h2 class="text-lg font-semibold tracking-tight">Local Sync</h2>
                    <p class="text-sm text-muted-foreground">Preview and sync changes</p>
                </div>
            </div>
            <button class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm transition-colors hover:bg-primary/90 disabled:pointer-events-none disabled:opacity-50" :disabled="loadingAction || !selectedTarget" @click="startOperation('preview', { label: 'Preview changes' })">
                <svg class="mr-2 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.964 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                Preview
            </button>
        </div>

        <div class="mb-6">
            <div class="inline-flex items-center rounded-lg bg-muted p-1">
                <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium transition-all" :class="tab === 'overview' ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'" @click="tab = 'overview'">Overview</button>
                <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium transition-all" :class="tab === 'targets' ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'" @click="tab = 'targets'">Targets</button>
                <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium transition-all" :class="tab === 'settings' ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'" @click="tab = 'settings'">Settings</button>
                <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium transition-all" :class="tab === 'ignore' ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'" @click="tab = 'ignore'">Ignore</button>
                <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium transition-all" :class="tab === 'activity' ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'" @click="tab = 'activity'">Activity</button>
            </div>
        </div>

        <section x-show="tab === 'overview'" class="grid gap-4 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                <div class="rounded-lg border bg-card shadow-sm">
                    <div class="flex items-center justify-between border-b px-4 py-3">
                        <h3 class="text-sm font-medium">Sync Workspace</h3>
                        <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium"
                              :class="{
                                  'bg-primary text-primary-foreground border-transparent': strategy === 'preview',
                                  'bg-blue-50 text-blue-700 border-blue-200': strategy === 'production-first',
                                  'bg-violet-50 text-violet-700 border-violet-200': strategy === 'local-first'
                              }"
                              x-text="strategy === 'preview' ? 'Preview' : (strategy === 'production-first' ? 'Production first' : 'Local first')"></span>
                    </div>
                    <div class="p-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-sm font-medium leading-none">Target</label>
                                <select x-model="selectedTarget" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50">
                                    <option value="">Select target</option>
                                    @foreach ($targets as $target)
                                        <option value="{{ $target['name'] }}">{{ $target['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium leading-none">Strategy</label>
                                <select x-model="strategy" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50">
                                    <option value="preview">Preview only</option>
                                    <option value="production-first">Production first</option>
                                    <option value="local-first">Local first</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-xs text-muted-foreground">Endpoint</p>
                                <p class="text-sm font-medium" x-text="selectedTarget || 'No target selected'"></p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button class="inline-flex items-center justify-center rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground shadow-sm transition-colors hover:bg-primary/90 disabled:pointer-events-none disabled:opacity-50" :disabled="loadingAction || !selectedTarget" @click="startOperation('preview', { label: 'Preview changes' })">
                                    <svg class="mr-1.5 size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.964 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                    Preview
                                </button>
                                <template x-if="previewResult && strategy === 'production-first'">
                                    <button class="inline-flex items-center justify-center rounded-md border border-input bg-transparent px-3 py-1.5 text-xs font-medium shadow-sm transition-colors hover:bg-accent hover:text-accent-foreground disabled:pointer-events-none disabled:opacity-50" :disabled="loadingAction" @click="openConfirm('apply-production-first', 'Pull production')">
                                        <svg class="mr-1.5 size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" /></svg>
                                        Pull
                                    </button>
                                </template>
                                <template x-if="previewResult && strategy === 'local-first'">
                                    <button class="inline-flex items-center justify-center rounded-md border border-input bg-transparent px-3 py-1.5 text-xs font-medium shadow-sm transition-colors hover:bg-accent hover:text-accent-foreground disabled:pointer-events-none disabled:opacity-50" :disabled="loadingAction" @click="openConfirm('apply-local-first', 'Push local')">
                                        <svg class="mr-1.5 size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M12 9.75 12 3m0 0 4.5 4.5M12 3l-4.5 4.5" /></svg>
                                        Push
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border bg-card shadow-sm">
                    <div class="border-b px-4 py-3">
                        <h3 class="text-sm font-medium">Preview Result</h3>
                    </div>
                    <div class="p-4">
                        <template x-if="previewResult">
                            <div class="space-y-4">
                                <div class="grid gap-3 sm:grid-cols-3">
                                    <div class="rounded-md border bg-muted/50 p-3">
                                        <p class="text-xs text-muted-foreground">Changed</p>
                                        <p class="mt-1 text-2xl font-bold" x-text="previewResult.summary?.modify ?? 0"></p>
                                    </div>
                                    <div class="rounded-md border bg-blue-50/50 p-3">
                                        <p class="text-xs text-muted-foreground">Incoming</p>
                                        <p class="mt-1 text-2xl font-bold text-blue-700" x-text="previewResult.summary?.add ?? 0"></p>
                                    </div>
                                    <div class="rounded-md border bg-violet-50/50 p-3">
                                        <p class="text-xs text-muted-foreground">Local only</p>
                                        <p class="mt-1 text-2xl font-bold text-violet-700" x-text="previewResult.summary?.delete_later ?? 0"></p>
                                    </div>
                                </div>

                                <div x-show="previewResult.files?.length">
                                    <p class="mb-2 text-xs font-medium text-muted-foreground">Files</p>
                                    <div class="divide-y rounded-md border">
                                        <template x-for="file in (previewResult.files || []).slice(0, 8)" :key="file.path">
                                            <div class="flex items-center justify-between px-3 py-2.5">
                                                <div class="min-w-0 flex-1">
                                                    <p class="truncate text-sm font-medium" x-text="file.path"></p>
                                                    <p class="truncate text-xs font-mono text-muted-foreground" x-text="file.hash"></p>
                                                </div>
                                                <span class="ml-3 shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                                                      :class="{
                                                          'bg-amber-50 text-amber-700': file.status === 'modified',
                                                          'bg-blue-50 text-blue-700': file.status === 'added',
                                                          'bg-violet-50 text-violet-700': file.status === 'deleted'
                                                      }"
                                                      x-text="file.status"></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <details class="rounded-md border">
                                    <summary class="cursor-pointer px-3 py-2 text-sm font-medium">Raw payload</summary>
                                    <div class="border-t p-3">
                                        <pre class="overflow-x-auto rounded-md bg-muted p-3 text-xs" x-text="JSON.stringify(previewResult, null, 2)"></pre>
                                    </div>
                                </details>
                            </div>
                        </template>

                        <div class="flex flex-col items-center justify-center py-12 text-center" x-show="!previewResult">
                            <svg class="mb-3 size-8 text-muted-foreground/50" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.964 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                            <p class="text-sm text-muted-foreground">Run preview to see changes</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border bg-card shadow-sm">
                    <div class="border-b px-4 py-3">
                        <h3 class="text-sm font-medium">Last Syncs</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                            <tr class="border-b bg-muted/50">
                                <th class="px-4 py-2 text-left text-xs font-medium text-muted-foreground">Version</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-muted-foreground">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-muted-foreground">Direction</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-muted-foreground">Strategy</th>
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
                                    <td class="px-4 py-2.5 text-muted-foreground">{{ data_get($version->metadata, 'strategy', '-') }}</td>
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
                        <h3 class="text-sm font-medium">Status</h3>
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
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <p class="text-xs text-muted-foreground">Target</p>
                                <p class="text-sm font-medium truncate" x-text="selectedTarget || '—'"></p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Strategy</p>
                                <p class="text-sm font-medium" x-text="strategy.split('-').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ')"></p>
                            </div>
                        </div>

                        <div x-show="activeOperation">
                            <p class="text-xs text-muted-foreground" x-text="activeOperation?.message"></p>
                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted">
                                <div class="h-full bg-primary transition-all duration-300" :style="`width: ${activeOperation?.progress || 0}%`"></div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-md border bg-muted/50 p-3">
                                <p class="text-xs text-muted-foreground">Recent changes</p>
                                <p class="mt-1 text-lg font-semibold">{{ $summary['recent_changes'] ?? 0 }}</p>
                            </div>
                            <div class="rounded-md border bg-red-50/50 p-3">
                                <p class="text-xs text-muted-foreground">Failed runs</p>
                                <p class="mt-1 text-lg font-semibold text-red-700">{{ $summary['failed_versions'] ?? 0 }}</p>
                            </div>
                        </div>

                        <button class="inline-flex w-full items-center justify-center rounded-md border border-input bg-transparent px-3 py-2 text-sm font-medium shadow-sm transition-colors hover:bg-accent hover:text-accent-foreground disabled:pointer-events-none disabled:opacity-50" :disabled="loadingAction" @click="openConfirm('restore-local', 'Restore local backup')">
                            <svg class="mr-2 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" /></svg>
                            Restore backup
                        </button>
                    </div>
                </div>
            </aside>
        </section>

        <section x-show="tab === 'targets'">
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-lg border bg-card shadow-sm">
                    <div class="border-b px-4 py-3">
                        <h3 class="text-sm font-medium">Add Target</h3>
                    </div>
                    <div class="p-4">
                        <div class="space-y-4">
                            <div class="space-y-2">
                                <label class="text-sm font-medium leading-none">Name</label>
                                <input x-model="targetForm.name" placeholder="production-main" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium leading-none">Base URL</label>
                                <input x-model="targetForm.url" placeholder="https://production.example.com" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium leading-none">API Key</label>
                                <input x-model="targetForm.api_key" placeholder="sync-api-key" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium leading-none">Source App ID</label>
                                <input x-model="targetForm.source_app_id" placeholder="demo-local" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                            </div>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" x-model="targetForm.is_default" class="size-4 rounded border-input text-primary focus:ring-ring">
                                <span class="text-sm text-muted-foreground">Use as default</span>
                            </label>
                            <button class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm transition-colors hover:bg-primary/90" @click="saveTarget">Save target</button>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border bg-card shadow-sm">
                    <div class="border-b px-4 py-3">
                        <h3 class="text-sm font-medium">Saved Targets</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                            <tr class="border-b bg-muted/50">
                                <th class="px-4 py-2 text-left text-xs font-medium text-muted-foreground">Name</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-muted-foreground">URL</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-muted-foreground">Default</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-muted-foreground"></th>
                            </tr>
                            </thead>
                            <tbody>
                            <template x-for="target in managedTargets" :key="target.id || target.name">
                                <tr class="border-b last:border-b-0">
                                    <td class="px-4 py-2.5 font-medium" x-text="target.name"></td>
                                    <td class="px-4 py-2.5"><code class="rounded bg-muted px-1.5 py-0.5 text-xs font-mono" x-text="target.url"></code></td>
                                    <td class="px-4 py-2.5">
                                        <template x-if="target.is_default">
                                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Default</span>
                                        </template>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center gap-2">
                                            <button type="button" class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium hover:bg-accent" @click="editTarget(target)">Edit</button>
                                            <button type="button" class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50" @click="deleteTarget(target.id)" x-show="target.id">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <section x-show="tab === 'settings'">
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-lg border bg-card shadow-sm">
                    <div class="border-b px-4 py-3">
                        <h3 class="text-sm font-medium">Transport</h3>
                    </div>
                    <div class="p-4 space-y-4">
                        <div class="space-y-2">
                            <label class="text-sm font-medium leading-none">Default Strategy</label>
                            <select x-model="settingsForm.default_strategy" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                <option value="preview">Preview only</option>
                                <option value="production-first">Production first</option>
                                <option value="local-first">Local first</option>
                            </select>
                        </div>
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

        <section x-show="tab === 'ignore'">
            <div class="rounded-lg border bg-card shadow-sm">
                <div class="flex items-center justify-between border-b px-4 py-3">
                    <h3 class="text-sm font-medium">Ignored Paths</h3>
                    <span class="inline-flex items-center rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium" x-text="ignoreDefaults.length + ignoreCustom.length + ' patterns'"></span>
                </div>
                <div class="p-4 space-y-4">
                    <div class="flex gap-2">
                        <input x-model="newIgnorePattern" @keydown.enter="addIgnorePattern" placeholder="e.g. public/uploads/" class="flex h-9 flex-1 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                        <button class="inline-flex shrink-0 items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm transition-colors hover:bg-primary/90 disabled:opacity-50" :disabled="loadingIgnore || !newIgnorePattern.trim()" @click="addIgnorePattern">
                            Add
                        </button>
                    </div>

                    <div class="space-y-3">
                        <template x-if="ignoreCustom.length > 0">
                            <div>
                                <p class="mb-2 text-xs font-medium text-muted-foreground uppercase tracking-wide">Custom</p>
                                <div class="divide-y rounded-md border">
                                    <template x-for="pattern in ignoreCustom" :key="pattern">
                                        <div class="flex items-center justify-between px-3 py-2">
                                            <code class="text-sm" x-text="pattern"></code>
                                            <button class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 disabled:opacity-50" :disabled="loadingIgnore" @click="removeIgnorePattern(pattern)">Remove</button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <template x-if="ignoreDefaults.length > 0">
                            <div>
                                <p class="mb-2 text-xs font-medium text-muted-foreground uppercase tracking-wide">Defaults</p>
                                <div class="divide-y rounded-md border bg-muted/30">
                                    <template x-for="pattern in ignoreDefaults" :key="pattern">
                                        <div class="flex items-center justify-between px-3 py-2">
                                            <code class="text-sm text-muted-foreground" x-text="pattern"></code>
                                            <span class="text-xs text-muted-foreground">Default</span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <template x-if="ignoreCustom.length === 0 && ignoreDefaults.length === 0">
                            <div class="flex flex-col items-center justify-center py-8 text-center">
                                <svg class="mb-2 size-6 text-muted-foreground/50" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 0 0 0 3.98 8.223" /></svg>
                                <p class="text-sm text-muted-foreground">No patterns configured</p>
                            </div>
                        </template>
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
