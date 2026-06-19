<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAdminRoleController extends Controller
{
    public const ALLOWED_PERMISSIONS = [
        'overview',
        'alerts',
        'companies',
        'companies.manage',
        'subscriptions',
        'billing',
        'plans',
        'audit',
    ];

    public function index(): JsonResponse
    {
        $roles = AdminRole::query()
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $roles->map(fn (AdminRole $r): array => $this->serialize($r))->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'in:'.implode(',', self::ALLOWED_PERMISSIONS)],
        ]);

        $role = AdminRole::create([
            'name' => $validated['name'],
            'permissions' => array_values(array_unique($validated['permissions'])),
        ]);

        return response()->json(['data' => $this->serialize($role->loadCount('users'))], Response::HTTP_CREATED);
    }

    public function update(Request $request, AdminRole $adminRole): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'in:'.implode(',', self::ALLOWED_PERMISSIONS)],
        ]);

        $data = [];
        if (isset($validated['name'])) {
            $data['name'] = $validated['name'];
        }
        if (isset($validated['permissions'])) {
            $data['permissions'] = array_values(array_unique($validated['permissions']));
        }

        $adminRole->update($data);

        return response()->json(['data' => $this->serialize($adminRole->refresh()->loadCount('users'))]);
    }

    public function destroy(AdminRole $adminRole): JsonResponse
    {
        $usersCount = $adminRole->users()->count();
        if ($usersCount > 0) {
            return response()->json([
                'message' => "Não é possível excluir este perfil pois {$usersCount} usuário(s) estão associados a ele.",
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $adminRole->delete();

        return response()->json(['message' => 'Perfil de acesso excluído com sucesso.']);
    }

    private function serialize(AdminRole $role): array
    {
        return [
            'id' => $role->getKey(),
            'name' => $role->name,
            'permissions' => $role->permissions ?? [],
            'users_count' => $role->users_count ?? null,
            'created_at' => $role->created_at?->toISOString(),
        ];
    }
}
