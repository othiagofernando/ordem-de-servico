<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Auth\Models\ApiToken;
use App\Domains\Auth\Models\Membership;
use App\Models\User;
use Illuminate\Support\Str;

class IssueApiToken
{
    /**
     * @return array{plain_text_token: string, api_token: ApiToken}
     */
    public function handle(User $user, Membership $membership, string $name = 'auth'): array
    {
        $plainTextToken = Str::random(64);

        $apiToken = ApiToken::query()->create([
            'company_id' => $membership->company_id,
            'user_id' => $user->getKey(),
            'name' => $name,
            'token_hash' => hash('sha256', $plainTextToken),
            'expires_at' => now()->addDays(30),
        ]);

        return [
            'plain_text_token' => $plainTextToken,
            'api_token' => $apiToken,
        ];
    }
}
