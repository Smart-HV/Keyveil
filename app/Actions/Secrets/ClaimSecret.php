<?php

namespace App\Actions\Secrets;

use App\Exceptions\Secrets\SecretUnavailable;
use App\Models\Secret;
use Illuminate\Support\Facades\DB;

/**
 * Atomically consume exactly one view of a secret and return its envelope.
 *
 * PostgreSQL row lock + transaction is the source of truth for view
 * consumption. When the last view is consumed, the row is physically deleted
 * inside the same transaction, before the response is sent.
 */
class ClaimSecret
{
    /**
     * @return array{crypto_version: int, envelope: array<string, mixed>}
     *
     * @throws SecretUnavailable
     */
    public function handle(string $publicId): array
    {
        $claimed = DB::transaction(function () use ($publicId): ?array {
            $secret = Secret::query()
                ->where('public_id', $publicId)
                ->lockForUpdate()
                ->first();

            if ($secret === null) {
                return null;
            }

            if ($secret->expires_at->isPast() || $secret->view_count >= $secret->max_views) {
                $secret->delete();

                return null;
            }

            $claimed = [
                'crypto_version' => $secret->crypto_version,
                'envelope' => $secret->envelope,
            ];

            $nextViewCount = $secret->view_count + 1;

            if ($nextViewCount >= $secret->max_views) {
                $secret->delete();
            } else {
                $secret->view_count = $nextViewCount;
                $secret->save();
            }

            return $claimed;
        }, attempts: 3);

        return $claimed ?? throw new SecretUnavailable;
    }
}
