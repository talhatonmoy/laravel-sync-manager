# UPGRADE GUIDE

## 0.x → 1.0.0 (breaking)

### receiver.enabled defaults to `false`

**Before:** installing the package exposed the sync receiver API endpoints
immediately; opt-in was via `false`.

**After:** the receiver is **disabled by default**. You must explicitly set
the env var or config on the machine that acts as the receiver:

```dotenv
# .env (receiver/production)
SYNC_MANAGER_RECEIVER_ENABLED=true
SYNC_MANAGER_API_KEY="your-strong-secret-here"
```

A receiver machine with `api_key = 'change-me'` is **rejected** in all
environments except `local`.

**Migration:** add the two lines above to your production `.env` before
deploying 1.0.0.

---

### api_key encrypted at rest

The `SyncTarget.api_key` field is now encrypted using Laravel's
`Crypt::encryptString` when written and decrypted transparently on read.
Legacy plaintext values continue to work.

No action required.
`api_key` is also hidden from JSON serialization (`$hidden`), so the
dashboard no longer exposes the shared secret in the page HTML or API
responses. Settings save remains write-only — the UI sends a new key
value or leaves the field empty to keep the existing one.

---

### transport.verify_ssl defaults to `true` (and is now wired)

**Before:** the config existed but was never read by the HTTP client.

**After:** `IncrementalTransport` applies `->withOptions(['verify' => …])`
to every outgoing request. The default is now `true`.

If you need to disable TLS verification for self-signed certificates in
development, set `SYNC_MANAGER_VERIFY_SSL=false` in `.env`.

---

### SecurityGate enforced on receiver writes

The dangerous-pattern denylist (`.env`, `*.key`, `*.sqlite`, `artisan`,
`composer.json`, `*.pem`, `phpunit.xml`, `auth.json`, `.git/*`, etc.) is
now enforced on **both** the sender's file scan **and** every receiver
write path (`ApplyService::commit`, `RollbackService`, `ProductionPullService`).

Writes to `config/sync.security.dangerous_patterns` are blocked at the
receiver regardless of what the sender attempts to push.

A new optional feature **writable_subtrees** allows limiting receiver
writes to specific subdirectories. When the list is empty all paths are
allowed subject to the denylist.

```php
// config/sync.php
'security' => [
    'writable_subtrees' => ['app/', 'resources/', 'routes/'],
],
```

---

### Default-key guard applies in ALL non-local environments

**Before:** the `change-me` default key was only rejected when
`APP_ENV=production`.

**After:** rejected in **any** environment that is not `local`
(staging, demo, preview, CI, etc.).

**Migration:** always set a strong `SYNC_MANAGER_API_KEY` on any
non-local deployment.

---

### downloadObject is now HMAC-signed

The receiver's `GET /{prefix}/objects/{hash}` endpoint now verifies
`X-Sync-Timestamp`, `X-Sync-Nonce`, and `X-Sync-Signature` headers
(in addition to the existing `X-Sync-Key` token). The client
(`IncrementalTransport::downloadObject`) sends these automatically.

This is fully backward-compatible for the built-in sender/receiver.
No migration needed.

---

### Upload size limit enforced (50 MB)

`ReceiverController::uploadObject` checks `Content-Length` header and
returns 413 if it exceeds `sync.objects.max_size_bytes` (default
50 MB). Configurable via `SYNC_MANAGER_OBJECT_MAX_SIZE`.

---

### SSRF mitigation (outbound)

Outbound sync HTTP requests to private/loopback IPs (127.x, 10.x,
192.168.x, 172.16-31.x, 169.254.x, localhost, *.local) are now
blocked by default. Disable with
`SYNC_MANAGER_BLOCK_PRIVATE_IPS=false` if needed.

---

### delete_later now physically removes files

A sync file with `status: delete_later` previously only untracked the
path from the state; the file remained on disk. It is now deleted.

---

### Object store GC

`storeContents` and `storeFile` now set `reference_count = 1` on newly
created objects. Run the new `sync:prune-objects` artisan command to
remove orphaned blobs (reference_count <= 0). Use `--dry-run` to
preview.

---

### Removed / changed config keys

| Key | Old default | New default |
|-----|-------------|-------------|
| `receiver.enabled` | `true` | `false` |
| `transport.verify_ssl` | `false` | `true` |
| `advanced.conflict_detection` | `true` (code) / `false` (config) | `false` (aligned) |
| `objects.max_size_bytes` | *(not present)* | `52428800` (50 MB) |
| `advanced.block_private_ips` | *(not present)* | `true` |

### New config keys

| Key | Default | Purpose |
|-----|---------|---------|
| `security.writable_subtrees` | `[]` | Allow-list for receiver writes |
| `objects.max_size_bytes` | `52428800` | Max upload size |
| `ui.require_auth` | `false` | Gate UI in all envs |
| `advanced.block_private_ips` | `true` | SSRF mitigation |

---

## Troubleshooting

**Receiver returns 404 on sync endpoints.**
Ensure `SYNC_MANAGER_RECEIVER_ENABLED=true` on the receiver machine.

**Receiver rejects commit with `Dangerous file detected`.**
The file matches a pattern in `security.dangerous_patterns`. Check the
path; if it's safe for your use case, add it to `writable_subtrees` or
remove the offending pattern.

**Receiver rejects commit with `outside the allowed writable subtrees`.**
The `security.writable_subtrees` array is set and the file path doesn't
fall under any of the listed prefixes. Add the needed prefix or leave
the array empty to allow all paths (subject to the denylist).

**Outbound sync fails with `blocked` error.**
Your target URL resolves to a private/loopback IP. Set the target to a
public hostname, or disable the check with
`SYNC_MANAGER_BLOCK_PRIVATE_IPS=false`.
