<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>DeployCar Sync Manager</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style type="text/tailwindcss">
        @theme {
            --color-background: #fafafa;
            --color-foreground: #0a0a0a;
            --color-card: #ffffff;
            --color-card-foreground: #0a0a0a;
            --color-popover: #ffffff;
            --color-popover-foreground: #0a0a0a;
            --color-primary: #0a0a0a;
            --color-primary-foreground: #fafafa;
            --color-secondary: #f5f5f5;
            --color-secondary-foreground: #0a0a0a;
            --color-muted: #f5f5f5;
            --color-muted-foreground: #737373;
            --color-accent: #f5f5f5;
            --color-accent-foreground: #0a0a0a;
            --color-destructive: #ef4444;
            --color-destructive-foreground: #fafafa;
            --color-success: #15803d;
            --color-success-foreground: #ffffff;
            --color-success-bg: #f0fdf4;
            --color-warning: #ca8a04;
            --color-warning-foreground: #ffffff;
            --color-warning-bg: #fffbeb;
            --color-border: #e5e5e5;
            --color-input: #e5e5e5;
            --color-ring: #0a0a0a;
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.625rem;
            --font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif;
            --font-mono: 'JetBrains Mono', ui-monospace, monospace;
        }

        [x-cloak] { display: none !important; }

        body {
            font-family: var(--font-sans);
            background: var(--color-background);
            color: var(--color-foreground);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        code, pre {
            font-family: var(--font-mono);
        }
    </style>
</head>
<body>
    <div class="min-h-screen bg-background">
        <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-8">
            <header class="mb-6 flex flex-col gap-4 rounded-lg border bg-card p-4 sm:flex-row sm:items-center sm:justify-between sm:gap-0">
                <div class="flex items-center gap-3">
                    <div class="flex size-9 items-center justify-center rounded-lg bg-primary text-sm font-semibold text-primary-foreground">DC</div>
                    <div>
                        <h1 class="text-sm font-semibold leading-none">DeployCar Sync Manager</h1>
                        <p class="mt-1 text-xs text-muted-foreground">Incremental Laravel sync</p>
                    </div>
                </div>
                <nav class="flex items-center gap-1 rounded-md bg-muted p-1">
                    <a href="{{ route('sync-manager.local') }}" class="rounded-sm px-3 py-1.5 text-xs font-medium transition-colors {{ request()->routeIs('sync-manager.local') ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground' }}">Local</a>
                    <a href="{{ route('sync-manager.production') }}" class="rounded-sm px-3 py-1.5 text-xs font-medium transition-colors {{ request()->routeIs('sync-manager.production') ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground' }}">Production</a>
                </nav>
            </header>

            @yield('content')
        </div>
    </div>

    <script>
        function deploycarDashboard(config) {
            return {
                mode: config.mode,
                routes: config.routes,
                pollMs: config.pollMs || 1500,
                tab: 'overview',
                strategy: config.initial.defaultStrategy || 'preview',
                selectedTarget: config.initial.selectedTarget || '',
                versions: config.initial.versions || [],
                operations: config.initial.operations || [],
                activeOperation: config.initial.activeOperation || null,
                previewResult: null,
                toasts: [],
                loadingAction: false,
                summary: config.initial.summary || {},
                currentState: config.initial.currentState || {},
                targetForm: {
                    name: '',
                    url: '',
                    api_key: '',
                    source_app_id: '',
                    is_default: false,
                },
                managedTargets: config.initial.managedTargets || [],
                settingsForm: JSON.parse(JSON.stringify(config.initial.settings || {})),
                showConfirm: false,
                confirmPayload: null,
                ignoreDefaults: [],
                ignoreCustom: [],
                newIgnorePattern: '',
                loadingIgnore: false,

                init() {
                    if (this.activeOperation && ['queued', 'running'].includes(this.activeOperation.status)) {
                        this.pollOperation(this.activeOperation.operation_id);
                    }
                    if (this.routes.ignoreIndex) {
                        this.loadIgnorePatterns();
                    }
                },

                statusTone(status) {
                    if (status === 'success') return 'success';
                    if (status === 'failed') return 'danger';
                    if (status === 'running' || status === 'queued') return 'warning';
                    return '';
                },

                operationUrl(operationId) {
                    return this.routes.operationShow.replace('__OPERATION__', operationId);
                },

                deleteTargetUrl(targetId) {
                    return this.routes.targetDelete.replace('__TARGET__', targetId);
                },

                async request(url, options = {}) {
                    const headers = {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        ...(options.headers || {}),
                    };

                    if (! (options.body instanceof FormData)) {
                        headers['Content-Type'] = 'application/json';
                    }

                    const response = await fetch(url, {
                        method: options.method || 'GET',
                        headers,
                        body: options.body instanceof FormData ? options.body : (options.body ? JSON.stringify(options.body) : undefined),
                    });

                    const payload = await response.json().catch(() => ({}));

                    if (! response.ok) {
                        throw new Error(payload.message || 'Request failed.');
                    }

                    return payload;
                },

                toast(title, body, tone = 'success') {
                    const id = Date.now() + Math.random();
                    this.toasts.push({ id, title, body, tone });
                    window.setTimeout(() => {
                        this.toasts = this.toasts.filter((toast) => toast.id !== id);
                    }, 4000);
                },

                openConfirm(type, label, extra = {}) {
                    this.confirmPayload = { type, label, ...extra };
                    this.showConfirm = true;
                },

                async confirmAction() {
                    if (! this.confirmPayload) return;
                    this.showConfirm = false;
                    await this.startOperation(this.confirmPayload.type, this.confirmPayload);
                },

                async startOperation(type, extra = {}) {
                    this.loadingAction = true;

                    try {
                        const payload = {
                            type,
                            strategy: extra.strategy || this.strategy,
                            target: extra.target || this.selectedTarget || null,
                            version_id: extra.version_id || null,
                        };
                        const queued = await this.request(this.routes.operationStart, {
                            method: 'POST',
                            body: payload,
                        });

                        this.activeOperation = {
                            operation_id: queued.operation_id,
                            type,
                            status: 'queued',
                            stage: 'queued',
                            progress: 0,
                            message: 'Operation queued.',
                        };

                        this.toast('Operation started', `${extra.label || type} has been queued.`, 'warning');
                        this.pollOperation(queued.operation_id);
                    } catch (error) {
                        this.toast('Operation failed to start', error.message, 'danger');
                    } finally {
                        this.loadingAction = false;
                    }
                },

                async pollOperation(operationId) {
                    try {
                        const operation = await this.request(this.operationUrl(operationId));
                        this.activeOperation = operation;
                        this.operations = [operation, ...this.operations.filter((item) => item.operation_id !== operation.operation_id)].slice(0, 12);

                        if (operation.status === 'success') {
                            if (operation.type === 'preview') {
                                this.previewResult = operation.result || null;
                            }

                            this.toast('Operation complete', operation.message || 'Background work finished.', 'success');
                            if (operation.type !== 'preview') {
                                window.setTimeout(() => window.location.reload(), 1200);
                            }

                            return;
                        }

                        if (operation.status === 'failed') {
                            this.toast('Operation failed', operation.message || 'The operation did not complete.', 'danger');
                            return;
                        }

                        window.setTimeout(() => this.pollOperation(operationId), this.pollMs);
                    } catch (error) {
                        this.toast('Polling failed', error.message, 'danger');
                    }
                },

                async saveTarget() {
                    try {
                        const response = await this.request(this.routes.targetSave, {
                            method: 'POST',
                            body: this.targetForm,
                        });

                        const target = response.target;
                        const index = this.managedTargets.findIndex((item) => item.id === target.id || item.name === target.name);
                        if (index >= 0) {
                            this.managedTargets.splice(index, 1, target);
                        } else {
                            this.managedTargets.unshift(target);
                        }

                        if (target.is_default) {
                            this.managedTargets = this.managedTargets.map((item) => ({
                                ...item,
                                is_default: item.id === target.id,
                            }));
                            this.selectedTarget = target.name;
                        }

                        this.targetForm = { name: '', url: '', api_key: '', source_app_id: '', is_default: false };
                        this.toast('Target saved', `${target.name} is ready to use.`, 'success');
                    } catch (error) {
                        this.toast('Target save failed', error.message, 'danger');
                    }
                },

                editTarget(target) {
                    this.tab = 'configuration';
                    this.targetForm = {
                        name: target.name || '',
                        url: target.url || '',
                        api_key: target.api_key || '',
                        source_app_id: target.source_app_id || '',
                        is_default: !! target.is_default,
                    };
                },

                async deleteTarget(targetId) {
                    try {
                        await this.request(this.deleteTargetUrl(targetId), { method: 'DELETE' });
                        this.managedTargets = this.managedTargets.filter((target) => target.id !== targetId);
                        this.toast('Target deleted', 'The target has been removed.', 'success');
                    } catch (error) {
                        this.toast('Delete failed', error.message, 'danger');
                    }
                },

                async saveSettings() {
                    try {
                        const payload = {
                            default_strategy: this.settingsForm.default_strategy,
                            transport: this.settingsForm.transport,
                            receiver: this.settingsForm.receiver,
                            notifications: this.settingsForm.notifications,
                        };
                        const response = await this.request(this.routes.settingsSave, {
                            method: 'POST',
                            body: payload,
                        });
                        this.settingsForm = JSON.parse(JSON.stringify(response.settings || this.settingsForm));
                        this.strategy = this.settingsForm.default_strategy || this.strategy;
                        this.toast('Settings saved', 'Dashboard configuration was updated.', 'success');
                    } catch (error) {
                        this.toast('Settings save failed', error.message, 'danger');
                    }
                },

                async loadIgnorePatterns() {
                    try {
                        const data = await this.request(this.routes.ignoreIndex);
                        this.ignoreDefaults = data.defaults || [];
                        this.ignoreCustom = data.custom || [];
                    } catch (error) {
                        this.toast('Failed to load', error.message, 'danger');
                    }
                },

                async addIgnorePattern() {
                    if (! this.newIgnorePattern.trim()) return;
                    this.loadingIgnore = true;
                    try {
                        await this.request(this.routes.ignoreStore, {
                            method: 'POST',
                            body: { pattern: this.newIgnorePattern.trim() },
                        });
                        this.newIgnorePattern = '';
                        await this.loadIgnorePatterns();
                        this.toast('Pattern added', 'The pattern is now ignored.', 'success');
                    } catch (error) {
                        this.toast('Failed to add', error.message, 'danger');
                    } finally {
                        this.loadingIgnore = false;
                    }
                },

                async removeIgnorePattern(pattern) {
                    this.loadingIgnore = true;
                    try {
                        await this.request(this.routes.ignoreDelete, {
                            method: 'DELETE',
                            body: { pattern },
                        });
                        await this.loadIgnorePatterns();
                        this.toast('Pattern removed', 'The pattern will no longer be ignored.', 'success');
                    } catch (error) {
                        this.toast('Failed to remove', error.message, 'danger');
                    } finally {
                        this.loadingIgnore = false;
                    }
                },

                copy(text) {
                    navigator.clipboard?.writeText(text);
                    this.toast('Copied', 'Instruction copied to your clipboard.', 'success');
                },
            };
        }
    </script>

    @yield('scripts')
</body>
</html>
