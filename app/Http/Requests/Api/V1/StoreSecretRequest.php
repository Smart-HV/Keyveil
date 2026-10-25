<?php

namespace App\Http\Requests\Api\V1;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Structural validation of the opaque crypto envelope. The server checks
 * shapes, lengths and parameter bounds only; it never attempts decryption.
 */
class StoreSecretRequest extends FormRequest
{
    /**
     * Base64url without padding of exactly 12, 16 and 32 bytes.
     */
    public const BASE64URL_12_BYTES = '/^[A-Za-z0-9_-]{16}$/';

    public const BASE64URL_16_BYTES = '/^[A-Za-z0-9_-]{22}$/';

    public const BASE64URL_32_BYTES = '/^[A-Za-z0-9_-]{43}$/';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $passwordEnabled = $this->input('envelope.password.enabled') === true;

        return [
            'crypto_version' => ['required', 'integer:strict', 'in:1'],
            'envelope' => ['required', 'array:v,alg,iv,hkdf_salt,password,ciphertext', $this->envelopeSize(...)],
            'envelope.v' => ['required', 'integer:strict', 'same:crypto_version'],
            'envelope.alg' => ['required', 'string', 'in:A256GCM'],
            'envelope.iv' => ['required', 'string', 'regex:'.self::BASE64URL_12_BYTES],
            'envelope.hkdf_salt' => ['required', 'string', 'regex:'.self::BASE64URL_32_BYTES],
            'envelope.password' => ['required', 'array:enabled,kdf,salt,memory_kib,iterations,parallelism'],
            'envelope.password.enabled' => ['required', 'boolean:strict'],
            'envelope.password.kdf' => $passwordEnabled ? ['required', 'string', 'in:argon2id'] : ['prohibited'],
            'envelope.password.salt' => $passwordEnabled ? ['required', 'string', 'regex:'.self::BASE64URL_16_BYTES] : ['prohibited'],
            'envelope.password.memory_kib' => $passwordEnabled ? ['required', 'integer:strict', 'between:19456,262144'] : ['prohibited'],
            'envelope.password.iterations' => $passwordEnabled ? ['required', 'integer:strict', 'between:2,10'] : ['prohibited'],
            'envelope.password.parallelism' => $passwordEnabled ? ['required', 'integer:strict', 'between:1,4'] : ['prohibited'],
            'envelope.ciphertext' => ['required', 'string', 'min:22', 'regex:/^[A-Za-z0-9_-]+$/'],
            'password_protected' => ['required', 'boolean:strict', $this->matchesEnvelopePassword(...)],
            'expires_in' => ['required', 'integer:strict', 'between:'.config('secrets.min_ttl_seconds').','.config('secrets.max_ttl_seconds')],
            'max_views' => ['required', 'integer:strict', 'between:1,'.config('secrets.max_views')],
            'delete_token_hash' => ['required', 'string', 'regex:'.self::BASE64URL_32_BYTES],
        ];
    }

    /**
     * The serialized envelope must stay within the configured size limit.
     */
    protected function envelopeSize(string $attribute, mixed $value, Closure $fail): void
    {
        if (strlen((string) json_encode($value)) > config('secrets.max_envelope_bytes')) {
            $fail('The :attribute is too large.');
        }
    }

    /**
     * The password_protected flag must match the envelope password config.
     */
    protected function matchesEnvelopePassword(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value !== ($this->input('envelope.password.enabled') === true)) {
            $fail('The :attribute must match the envelope password configuration.');
        }
    }
}
