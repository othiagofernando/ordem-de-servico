<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class UserNotificationController extends Controller
{
    public function index(): JsonResponse
    {
        $membership = currentMembership();

        if ($membership === null) {
            return response()->json(['data' => [], 'meta' => ['unread_count' => 0]]);
        }

        $notifications = UserNotification::query()
            ->where('membership_id', $membership->getKey())
            ->latest()
            ->limit(30)
            ->get();

        $unreadCount = UserNotification::query()
            ->where('membership_id', $membership->getKey())
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'data' => $notifications->map(fn (UserNotification $n) => [
                'id'         => $n->id,
                'type'       => $n->type,
                'title'      => $n->title,
                'body'       => $n->body,
                'action_url' => $n->action_url,
                'read_at'    => $n->read_at?->toISOString(),
                'created_at' => $n->created_at->toISOString(),
            ]),
            'meta' => ['unread_count' => $unreadCount],
        ]);
    }

    public function markRead(UserNotification $notification): JsonResponse
    {
        $membership = currentMembership();

        if ($membership === null || $notification->membership_id !== $membership->getKey()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return response()->json(['data' => ['id' => $notification->id, 'read_at' => $notification->read_at?->toISOString()]]);
    }

    public function markAllRead(): JsonResponse
    {
        $membership = currentMembership();

        if ($membership === null) {
            abort(Response::HTTP_FORBIDDEN);
        }

        UserNotification::query()
            ->where('membership_id', $membership->getKey())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['data' => ['marked_read' => true]]);
    }
}
