<?php

namespace DeployCar\LaravelSyncManager\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class SyncTarget extends Model
{
    protected $table = 'sync_targets';

    protected $guarded = [];

    /**
     * Never expose the shared secret when the model is serialised to
     * arrays/JSON (e.g. rendered into the dashboard payload).
     *
     * @var list<string>
     */
    protected $hidden = ['api_key'];

    protected $casts = [
        'is_default' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Encrypt the API key at rest, decrypting transparently on read.
     *
     * Legacy plaintext values are tolerated so enabling encryption does not
     * break rows written before this change.
     */
    protected function apiKey(): Attribute
    {
        return Attribute::make(
            get: static function (?string $value): ?string {
                if ($value === null || $value === '') {
                    return $value;
                }

                try {
                    return Crypt::decryptString($value);
                } catch (DecryptException) {
                    return $value;
                }
            },
            set: static fn (?string $value): ?string => ($value === null || $value === '')
                ? null
                : Crypt::encryptString($value),
        );
    }
}
