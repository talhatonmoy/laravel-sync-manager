# Laravel Sync Manager

Lightweight bidirectional file sync for Laravel with tracked state, atomicity guarantees, and rollback support. Designed for shared hosting without queue workers.

## What It Does

`laravel-sync-manager` syncs file changes between local development and production incrementally:

- **Local → Production** (`sync:send`, `sync:run`): Upload changed/added files; optionally delete removed ones.
- **Production → Local** (`sync:pull`): Download remote files to local.
- **Preview changes** (`sync:preview`): See what will sync **in under 5 seconds** (cached hashing, no remote HTTP).
- **Rollback** (`sync:rollback`): Revert production to a previous sync state.
- **Atomic applies**: All-or-nothing file writes with staging area + rename swap.

## Installation

```bash
composer require deploycar/laravel-sync-manager
php artisan migrate
```

Publish config (optional; everything is env-driven):
```bash
php artisan vendor:publish --tag=sync-manager-config
```

## Quick Start

### Sender (local development)

Set two env vars:
```env
SYNC_MANAGER_TARGET_URL=https://production.example.com
SYNC_MANAGER_API_KEY=your-shared-secret-key
```

Then:
```bash
php artisan sync:preview              # See what will sync (fast, local only)
php artisan sync:send                 # Sync to production
php artisan sync:send --no-delete     # Keep deleted files on production
```

### Receiver (production)

Set env vars:
```env
SYNC_MANAGER_RECEIVER_ENABLED=true
SYNC_MANAGER_API_KEY=your-shared-secret-key
```

The receiver runs as HTTP endpoints (`POST /sync/objects/check`, `POST /sync/objects/upload`, `POST /sync/commit`). No manual steps needed; the sender calls these automatically.

## How It Works

### 1. Local Scan with Cache

The first `sync:preview` or `sync:send` scans local files. Instead of re-hashing everything each time:
- Scan stores `path → (mtime, size, hash)` in `sync_local_cache` table.
- On next scan, if `mtime` and `size` haven't changed, reuse the cached hash.
- Only changed files get re-hashed.

**Result**: Second and subsequent scans are fast; preview completes in **<5 seconds** even on large trees.

### 2. Local-Only Preview

`sync:preview` compares:
- Fresh local scan (cache-accelerated) 
- `sync_target_states` table (last known production state)

**No HTTP call to production.** Just local comparison. Output:
```
Added:       3
Modified:    1
Deleted:     0
Total files: 4
```

Trade-off: If files were changed directly on production outside the tool, preview won't see them — they'll surface at send time. This is acceptable for incremental workflow where the sender is authoritative.

### 3. Incremental Manifest

`sync:send` generates a manifest of changed files + deleted files. The manifest includes:
- File path, content hash, size.
- Status: `add`, `modify`, `delete_later`.
- Signature (HMAC-SHA256 of manifest + shared API key for tampering detection).

Optional `--no-delete` flag filters out deleted files from the manifest.

### 4. Object Upload

For each changed/added file, the sender uploads the **content** (blob) to production's object store (`storage/app/private/sync-manager/objects/<hash>`). The hash is the key, so:
- Same file content = same hash = skip upload if already present.
- Only new content gets uploaded.
- Production keeps all object hashes for rollback.

### 5. Atomic Apply

Production receives the manifest and applies it:

**Phase 1 (Staging)**: Write all incoming files to a temporary staging directory (`storage/staging/<versionId>/<path>`), verifying each blob hash on write.

**Phase 2 (Swap)**: After all files staged successfully, atomic `rename()` from staging → destination. Deletes applied here too.

**Result**: If anything fails during staging, production is **never touched**. The swap is all-or-nothing per file (atomic on most filesystems). Backups taken before swap as a second safety net.

### 6. State Tracking

After successful apply, production updates `sync_target_states` with the new file hashes. This becomes the baseline for the next preview.

History is kept in `sync_versions`, `sync_files`, `sync_logs` for auditing and rollback.

## Commands

### Sender (Local Development)

#### `sync:preview [target]`
Preview changes without applying. Target defaults to env `SYNC_MANAGER_TARGET_NAME`.
```bash
php artisan sync:preview                    # Preview to primary target
php artisan sync:preview --all              # Preview all files (no incremental)
```
Output: added, modified, unchanged, deleted counts + summary.

#### `sync:send [target]`
Send changes to production.
```bash
php artisan sync:send                       # Incremental send
php artisan sync:send --no-delete           # Don't delete removed files on production
php artisan sync:send --force               # Skip confirmation (CI/CD)
```

#### `sync:run [target]`
Alias for `sync:send`.

#### `sync:dry-run [target]`
Like preview but with more detail; shows the actual file list that will sync.

#### `sync:pull [target]`
Pull production files to local. **Destructive** — overwrites local files with remote.
```bash
php artisan sync:pull --force
```

#### `sync:scan [root]`
Scan local directory and display file manifest (debugging).
```bash
php artisan sync:scan                       # Scan SYNC_MANAGER_SOURCE_PATH
php artisan sync:scan /var/www/html         # Scan custom path
```

#### `sync:history [--limit=10]`
Show recent sync operations (when they started, ended, status, files changed).
```bash
php artisan sync:history --limit 20
```

### Receiver (Production)

No manual commands; the receiver is event-driven. HTTP endpoints handle:
- `POST /sync/objects/check` — Query which object hashes are present.
- `POST /sync/objects/upload` — Upload a file blob to object store.
- `POST /sync/commit` — Apply a manifest (stage + swap).

All endpoints require a valid API signature (HMAC-SHA256 using shared API key).

### Admin/Maintenance (Either Side)

#### `sync:rollback [--version=<id>]`
Revert to a previous sync state.
```bash
php artisan sync:rollback                   # Undo last sync
php artisan sync:rollback --version=abc123  # Rollback to specific version
```

#### `sync:restore-local`
Restore local files from a backup (if a previous sync was undone).
```bash
php artisan sync:restore-local --force
```

#### `sync:prune-objects [--keep-versions=5]`
Clean up old object store entries no longer needed.
```bash
php artisan sync:prune-objects              # Remove unreferenced objects
```

## Configuration

All configuration is **environment-based**. Publish `config/sync.php` to customize defaults:

```bash
php artisan vendor:publish --tag=sync-manager-config
```

Key env vars:

**Sender:**
- `SYNC_MANAGER_SOURCE_PATH` — Root directory to sync (default: project root).
- `SYNC_MANAGER_STORAGE_ROOT` — Where to store objects, backups, staging (default: `storage/app/private/sync-manager`).
- `SYNC_MANAGER_TARGET_URL` — Production receiver URL (required).
- `SYNC_MANAGER_TARGET_NAME` — Display name for target (default: hostname from URL).
- `SYNC_MANAGER_TARGET_API_KEY` — Shared secret for authentication (required, must be 32+ chars in production).

**Receiver:**
- `SYNC_MANAGER_RECEIVER_ENABLED` — Set to `true` to enable receiver endpoints (production).
- `SYNC_MANAGER_API_KEY` — Shared secret (same as sender's `SYNC_MANAGER_TARGET_API_KEY`).
- `SYNC_MANAGER_ROUTE_PREFIX` — URL path prefix for receiver (default: `sync`).

**Timing:**
- `SYNC_MANAGER_TIMEOUT` — HTTP request timeout in seconds (default: 30).
- `SYNC_MANAGER_NONCE_TTL` — Request signature expiry in seconds (default: 300).
- `SYNC_MANAGER_CLOCK_SKEW` — Allow time diff between sender/receiver (default: 300).

## Security

### API Key
The sender and receiver must share the same API key. Each request is signed with HMAC-SHA256 using this key + a nonce (one-time value + timestamp). 

**Production requirement**: Use a strong, randomly-generated API key (32+ characters). The default `'change-me'` will cause receiver to reject requests in production.

### Nonce Replay Protection
Each request includes a nonce. The receiver checks that the same nonce isn't used twice within the TTL window. 

**Critical for shared hosting**: The receiver **must** use `database` or `file` cache driver (not `array`) for replay protection to work across requests. If you see logs warning about `array` driver, reconfigure cache to `database`:

```env
CACHE_DRIVER=database
```

Then run:
```bash
php artisan cache:table
php artisan migrate
```

### File Patterns
By default, these files/paths are **never synced** (dangerous or system files):

```
.env, .env.*, .git, artisan, composer.json, composer.lock, *.key, *.pem,
vendor/, node_modules/, storage/logs, bootstrap/cache, .sqlite, .db, auth.json, phpunit.xml
```

Customize in `config/sync.php` under `security.dangerous_patterns`. Add writable subtrees if needed.

### Confirmations
Destructive commands (`sync:run`, `sync:pull`, `sync:rollback` in production) require explicit confirmation or `--force` flag.

## Performance

### Preview Speed

**Goal**: `<5 seconds` for full trees.

**How**:
- Local scan uses cache (mtime/size match = skip hashing).
- Comparison is in-memory (no remote calls).
- After first warm cache, subsequent previews are **<1 second** for unchanged files.

**Real-world example**: 500-file project, first preview ~3s (cache build). Second preview ~0.5s (only hashing changed files).

### Send Speed

Depends on changed-file count and their size:
- File comparison: local only, fast.
- Object upload: parallel streams to receiver.
- Apply: sender waits for receiver to stage + swap all files.

For 50MB of changed files on typical hosting, expect 2–5 minutes (limited by upload bandwidth).

### Rollback Speed

Restores all files from object store in sequence. ~30–60 seconds for 500 files, depending on disk I/O.

## Atomicity Guarantees

### Sender Side
- Manifest creation is all-or-nothing.
- If object upload fails mid-stream, the receiver rejects the manifest (missing objects).

### Receiver Side
- **Staging phase fails**: All files stay in staging; production untouched.
- **Swap phase fails**: Production may have partial swaps per file (filesystem atomic `rename()` limits), but backups are restored.
- **Net result**: On any error, production state is either unchanged or can be restored from backup.

**Not atomic across receiver restarts**: If the receiver process dies mid-swap, manual intervention may be needed. This is rare on managed hosting. Document your restore procedure.

## Limitations

### Single Target (Env-Driven)
Only one production target per env. Multi-target sync requires separate app instances or manual orchestration.

### No Conflict Resolution
If production files are modified outside the tool (direct SSH, other deployment tool), preview won't see them until the next send. Conflicts are detected at apply time and cause a 409 error; manual merge required.

### No Selective Sync
Can't sync only specific paths yet; the entire source tree is compared. Use `.syncignore` to exclude files.

### Network Reliability
Long syncs may timeout on slow/unreliable connections. Reduce `SYNC_MANAGER_OBJECT_MAX_SIZE` to upload smaller blobs more frequently.

## Troubleshooting

### "No sync target is configured"
`SYNC_MANAGER_TARGET_URL` is missing or empty. Set it in `.env`.

### "API signature invalid"
API keys don't match, or clock skew is too large. Check both sides have same `SYNC_MANAGER_API_KEY` and server times are synced.

### "Object not found in store"
Receiver object store is corrupted or pruned too aggressively. Don't run `sync:prune-objects` while syncs are in progress.

### Preview is slow (>5 seconds)
Cache is cold (first run) or most files are modified. Subsequent runs will be faster. If still slow, check disk I/O.

### Receiver endpoints return 500 errors
Check receiver logs: `storage/logs/`. Common issues: storage directory not writable, out of disk space, database connection lost.

### Receiver cache driver warning
If logs warn about `array` cache driver, nonce replay protection is not persistent. Reconfigure to `database` driver (see Security section).

## Development

Tests: `composer test`

The package includes ~54 tests covering:
- Scan cache logic (mtime/size skipping unchanged files).
- Local-only preview (no HTTP calls).
- Delete propagation (delete_later in manifest).
- Atomic staging + rename (all-or-nothing apply).
- API signing and replay protection.

## License

MIT
