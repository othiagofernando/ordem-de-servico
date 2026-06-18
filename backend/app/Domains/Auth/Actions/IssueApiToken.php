<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Auth\Models\ApiToken;
use App\Domains\Auth\Models\Membership;
use App\Domains\Companies\Models\Company;
use App\Models\User;
use Illuminate\Support\Str;

class IssueApiToken
{
    /**
     * @return array{plain_text_token: string, api_token: ApiToken}
     */
    public function handle(User $user, ?Membership $membership, string $name = 'auth'): array
    {
        $plainTextToken = Str::random(64);

        $companyId = $membership?->company_id;

        if ($membership === null) {
            abort_unless($user->super_admin, 500, 'Super admin token issuance requires a super admin user.');
        }

        $apiToken = ApiToken::query()->create([
            'company_id' => $companyId,
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
