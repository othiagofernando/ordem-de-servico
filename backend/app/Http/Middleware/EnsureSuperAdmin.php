<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next, string ...$allowedRoles): Response
    {
        $user = $request->user();

        if (! $user?->super_admin) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if (! empty($allowedRoles)) {
            $adminRole = $user->admin_role ?? 'super_admin';
            if (! in_array($adminRole, $allowedRoles, true)) {
                abort(Response::HTTP_FORBIDDEN, 'Permissão insuficiente.');
            }
        }

        return $next($request);
    }
}
