<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SecretFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * Encrypted secret envelope plus lifecycle metadata. The server never holds
 * the key, the plaintext or the optional password.
 *
 * @property int $id
 * @property string $public_id
 * @property int $crypto_version
 * @property array<string, mixed> $envelope
 * @property bool $password_protected
 * @property CarbonImmutable $expires_at
 * @property int $max_views
 * @property int $view_count
 * @property string $delete_token_hash
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Hidden(['envelope', 'delete_token_hash'])]
class Secret extends Model
{
    /** @use HasFactory<SecretFactory> */
    use HasFactory, MassPrunable;

    /**
     * Attributes are always assigned explicitly; nothing is mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = ['*'];

    /**
     * Generate a URL-safe public ID from 16 CSPRNG bytes (128 bit, 22 chars).
     */
    public static function generatePublicId(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    }

    /**
     * Hash the client supplied delete token hash once more for storage, so a
     * database leak does not yield a usable delete credential.
     */
    public static function hashDeleteToken(#[\SensitiveParameter] string $deleteTokenHash): string
    {
        return hash('sha256', $deleteTokenHash);
    }

    /**
     * Expired secrets are physically removed by the scheduled model:prune.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<=', now());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'crypto_version' => 'integer',
            'envelope' => 'array',
            'password_protected' => 'boolean',
            'expires_at' => 'datetime',
            'max_views' => 'integer',
            'view_count' => 'integer',
        ];
    }
}
