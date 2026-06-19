<?php

namespace App\Domains\Notifications\Actions;

use App\Models\UserNotification;

class CreateUserNotification
{
    public function handle(
        int $companyId,
        int $membershipId,
        string $type,
        string $title,
        string $body,
        ?string $actionUrl = null,
    ): UserNotification {
        return UserNotification::create([
            'company_id'    => $companyId,
            'membership_id' => $membershipId,
            'type'          => $type,
            'title'         => $title,
            'body'          => $body,
            'action_url'    => $actionUrl,
        ]);
    }
}
