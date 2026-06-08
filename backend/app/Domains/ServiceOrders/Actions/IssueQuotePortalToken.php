<?php

namespace App\Domains\ServiceOrders\Actions;

use App\Domains\ServiceOrders\Models\QuoteRevision;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class IssueQuotePortalToken
{
    /**
     * @return array{plain_text_token: string, expires_at: Carbon}
     */
    public function handle(QuoteRevision $quoteRevision): array
    {
        $plainTextToken = Str::random(40);
        $expiresAt = now()->addDays(2);

        $quoteRevision->forceFill([
            'portal_token_hash' => hash('sha256', $plainTextToken),
            'portal_token_expires_at' => $expiresAt,
        ])->save();

        return [
            'plain_text_token' => $plainTextToken,
            'expires_at' => $expiresAt,
        ];
    }
}
