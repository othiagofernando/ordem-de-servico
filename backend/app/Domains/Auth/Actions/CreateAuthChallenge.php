<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Auth\Models\AuthChallenge;
use App\Domains\Companies\Models\Company;
use App\Models\User;
use Illuminate\Support\Str;

class CreateAuthChallenge
{
    /**
     * @return array{plain_text_token: string, plain_text_code: ?string, challenge: AuthChallenge}
     */
    public function handle(
        User $user,
        ?Company $company,
        string $type,
        int $ttlMinutes,
        array $meta = [],
        bool $withNumericCode = false,
    ): array {
        $plainTextToken = Str::random(64);
        $plainTextCode = $withNumericCode ? str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT) : null;

        $challenge = AuthChallenge::query()->create([
            'user_id' => $user->getKey(),
            'company_id' => $company?->getKey(),
            'type' => $type,
            'token_hash' => hash('sha256', $plainTextToken),
            'code_hash' => $plainTextCode ? hash('sha256', $plainTextCode) : null,
            'expires_at' => now()->addMinutes($ttlMinutes),
            'meta' => $meta,
        ]);

        return [
            'plain_text_token' => $plainTextToken,
            'plain_text_code' => $plainTextCode,
            'challenge' => $challenge,
        ];
    }
}
