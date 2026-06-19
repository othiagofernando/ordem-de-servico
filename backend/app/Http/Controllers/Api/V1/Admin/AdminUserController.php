<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminRole;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Response;

class AdminUserController extends Controller
{
    public const ROLES = ['super_admin', 'support', 'billing', 'plans', 'viewer'];

    public function index(): JsonResponse
    {
        $users = User::query()
            ->where('super_admin', true)
            ->with('adminRoleModel')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $users->map(fn (User $u): array => $this->serialize($u))->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'admin_role' => ['required_without:admin_role_id', 'nullable', 'in:'.implode(',', self::ROLES)],
            'admin_role_id' => ['required_without:admin_role', 'nullable', 'exists:admin_roles,id'],
        ]);

        $userData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'super_admin' => true,
        ];

        if (! empty($validated['admin_role_id'])) {
            $userData['admin_role_id'] = $validated['admin_role_id'];
            $userData['admin_role'] = null;
        } else {
            $userData['admin_role'] = $validated['admin_role'];
            $userData['admin_role_id'] = null;
        }

        $user = User::create($userData);

        return response()->json(['data' => $this->serialize($user->load('adminRoleModel'))], Response::HTTP_CREATED);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless($user->super_admin, Response::HTTP_NOT_FOUND);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'admin_role' => ['sometimes', 'nullable', 'in:'.implode(',', self::ROLES)],
            'admin_role_id' => ['sometimes', 'nullable', 'exists:admin_roles,id'],
            'password' => ['sometimes', 'nullable', Password::defaults()],
        ]);

        $data = [];

        if (isset($validated['name'])) {
            $data['name'] = $validated['name'];
        }

        // Handle role assignment: custom role takes precedence
        if (array_key_exists('admin_role_id', $validated)) {
            if (! empty($validated['admin_role_id'])) {
                $data['admin_role_id'] = $validated['admin_role_id'];
                $data['admin_role'] = null;
            } else {
                $data['admin_role_id'] = null;
                if (isset($validated['admin_role'])) {
                    $data['admin_role'] = $validated['admin_role'];
                }
            }
        } elseif (array_key_exists('admin_role', $validated)) {
            $data['admin_role'] = $validated['admin_role'];
            $data['admin_role_id'] = null;
        }

        if (! empty($validated['password'])) {
            $data['password'] = $validated['password'];
        }

        $user->update($data);

        return response()->json(['data' => $this->serialize($user->refresh()->load('adminRoleModel'))]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_unless($user->super_admin, Response::HTTP_NOT_FOUND);

        if ($user->getKey() === $request->user()->getKey()) {
            return response()->json(['message' => 'Não é possível revogar o seu próprio acesso administrativo.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $superAdminCount = User::where('super_admin', true)->where('admin_role', 'super_admin')->count();
        if ($user->admin_role === 'super_admin' && $superAdminCount <= 1) {
            return response()->json(['message' => 'Deve existir ao menos um usuário com perfil Super Admin.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->update(['super_admin' => false, 'admin_role' => null, 'admin_role_id' => null]);

        return response()->json(['message' => 'Acesso administrativo revogado com sucesso.']);
    }

    private function serialize(User $user): array
    {
        $customRole = $user->relationLoaded('adminRoleModel') ? $user->adminRoleModel : null;

        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'admin_role' => $user->admin_role_id ? null : ($user->admin_role ?? 'super_admin'),
            'admin_role_id' => $user->admin_role_id,
            'admin_role_name' => $customRole?->name,
            'created_at' => $user->created_at?->toISOString(),
        ];
    }
}
