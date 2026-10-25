<?php

namespace Database\Factories;

use App\Models\Secret;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Secret>
 */
class SecretFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => Secret::generatePublicId(),
            'crypto_version' => 1,
            'envelope' => self::envelope(),
            'password_protected' => false,
            'expires_at' => now()->addHour(),
            'max_views' => 1,
            'view_count' => 0,
            'delete_token_hash' => Secret::hashDeleteToken(self::base64url(32)),
        ];
    }

    /**
     * Indicate that the secret has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subSecond(),
        ]);
    }

    /**
     * Indicate that the secret is protected by an additional password.
     */
    public function passwordProtected(): static
    {
        return $this->state(fn (array $attributes) => [
            'password_protected' => true,
            'envelope' => self::envelope(passwordProtected: true),
        ]);
    }

    /**
     * Set the maximum number of views.
     */
    public function views(int $maxViews): static
    {
        return $this->state(fn (array $attributes) => [
            'max_views' => $maxViews,
        ]);
    }

    /**
     * Use the given client side delete token hash (base64url SHA-256).
     */
    public function withDeleteTokenHash(string $deleteTokenHash): static
    {
        return $this->state(fn (array $attributes) => [
            'delete_token_hash' => Secret::hashDeleteToken($deleteTokenHash),
        ]);
    }

    /**
     * Build a structurally valid (but meaningless) v1 crypto envelope.
     *
     * @return array<string, mixed>
     */
    public static function envelope(bool $passwordProtected = false): array
    {
        return [
            'v' => 1,
            'alg' => 'A256GCM',
            'iv' => self::base64url(12),
            'hkdf_salt' => self::base64url(32),
            'password' => $passwordProtected
                ? [
                    'enabled' => true,
                    'kdf' => 'argon2id',
                    'salt' => self::base64url(16),
                    'memory_kib' => 65536,
                    'iterations' => 3,
                    'parallelism' => 1,
                ]
                : ['enabled' => false],
            'ciphertext' => self::base64url(48),
        ];
    }

    /**
     * Random bytes encoded as base64url without padding.
     *
     * @param  positive-int  $bytes
     */
    public static function base64url(int $bytes): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
}
