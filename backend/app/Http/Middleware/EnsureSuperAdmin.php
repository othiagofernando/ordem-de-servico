<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    /**
     * Maps fixed role names (as used in route middleware) to the admin sections they cover.
     * Used to resolve whether a custom-role user has access.
     */
    private const ROLE_SECTION_MAP = [
        'support' => ['alerts', 'companies', 'companies.manage', 'subscriptions', 'audit'],
        'billing' => ['billing', 'subscriptions'],
        'plans'   => ['plans'],
        'viewer'  => ['companies', 'subscriptions', 'audit'],
    ];

    public function handle(Request $request, Closure $next, string ...$allowedRoles): Response
    {
        $user = $request->user();

        if (! $user?->super_admin) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if (! empty($allowedRoles)) {
            // Super admin fixed role always passes
            $adminRole = $user->admin_role ?? 'super_admin';
            if ($adminRole === 'super_admin') {
                return $next($request);
            }

            // User with a custom role: check if their permissions intersect
            // with the sections required by the allowed roles
            if ($user->admin_role_id !== null) {
                $customRole = $user->adminRoleModel;
                if ($customRole !== null) {
                    $userPermissions = $customRole->permissions ?? [];
                    $requiredSections = $this->sectionsForRoles($allowedRoles);

                    if (! empty(array_intersect($userPermissions, $requiredSections))) {
                        return $next($request);
                    }
                }

                abort(Response::HTTP_FORBIDDEN, 'Permissão insuficiente.');
            }

            // Fixed role check
            if (! in_array($adminRole, $allowedRoles, true)) {
                abort(Response::HTTP_FORBIDDEN, 'Permissão insuficiente.');
            }
        }

        return $next($request);
    }

    /**
     * Collect all sections required by the given list of allowed roles.
     *
     * @param  string[]  $roles
     * @return string[]
     */
    private function sectionsForRoles(array $roles): array
    {
        $sections = [];
        foreach ($roles as $role) {
            if (isset(self::ROLE_SECTION_MAP[$role])) {
                $sections = array_merge($sections, self::ROLE_SECTION_MAP[$role]);
            }
        }

        return array_unique($sections);
    }
}
