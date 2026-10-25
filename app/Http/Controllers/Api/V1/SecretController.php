<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Secrets\ClaimSecret;
use App\Exceptions\Secrets\SecretUnavailable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeleteSecretRequest;
use App\Http\Requests\Api\V1\StoreSecretRequest;
use App\Models\Secret;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class SecretController extends Controller
{
    /**
     * Store an encrypted envelope. public_id, expires_at and view_count are
     * always set server-side; every attribute is mapped explicitly.
     */
    public function store(StoreSecretRequest $request): JsonResponse
    {
        $secret = retry(3, function () use ($request): Secret {
            $secret = new Secret;
            $secret->public_id = Secret::generatePublicId();
            $secret->crypto_version = $request->integer('crypto_version');
            $secret->envelope = $request->validated('envelope');
            $secret->password_protected = $request->boolean('password_protected');
            $secret->expires_at = now()->addSeconds($request->integer('expires_in'));
            $secret->max_views = $request->integer('max_views');
            $secret->view_count = 0;
            $secret->delete_token_hash = Secret::hashDeleteToken($request->validated('delete_token_hash'));
            $secret->save();

            return $secret;
        }, when: fn (\Throwable $e) => $e instanceof UniqueConstraintViolationException);

        return response()->json([
            'data' => [
                'public_id' => $secret->public_id,
                'expires_at' => $secret->expires_at->utc()->toIso8601ZuluString(),
                'max_views' => $secret->max_views,
            ],
        ], 201);
    }

    /**
     * Consume one view and return the envelope in the same atomic step.
     */
    public function claim(string $publicId, ClaimSecret $claimSecret): JsonResponse
    {
        return response()->json([
            'data' => $claimSecret->handle($publicId),
        ]);
    }

    /**
     * Physically delete a secret with its delete token hash. Unknown secrets
     * and wrong tokens produce the same response.
     */
    public function destroy(DeleteSecretRequest $request, string $publicId): Response
    {
        $secret = Secret::query()->where('public_id', $publicId)->first();

        $providedHash = Secret::hashDeleteToken($request->validated('delete_token_hash'));
        $storedHash = $secret->delete_token_hash ?? str_repeat('0', 64);

        if (! hash_equals($storedHash, $providedHash) || $secret === null) {
            throw new SecretUnavailable;
        }

        $secret->delete();

        return response()->noContent();
    }
}
