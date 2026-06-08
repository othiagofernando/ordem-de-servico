<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeMembershipPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $membership = currentMembership();

        if ($membership === null || ! $membership->hasPermission($permission)) {
            return $this->forbidden(sprintf('This membership lacks the [%s] permission.', $permission));
        }

        return $next($request);
    }

    protected function forbidden(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], Response::HTTP_FORBIDDEN);
    }
}
