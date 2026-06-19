<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('membership.{membershipId}', function ($user, int $membershipId): bool {
    $membership = currentMembership();
    return $membership !== null && (int) $membership->getKey() === $membershipId;
});
