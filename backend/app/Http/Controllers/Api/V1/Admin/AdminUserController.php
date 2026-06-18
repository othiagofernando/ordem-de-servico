<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
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
            'admin_role' => ['required', 'in:'.implode(',', self::ROLES)],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'super_admin' => true,
            'admin_role' => $validated['admin_role'],
        ]);

        return response()->json(['data' => $this->serialize($user)], Response::HTTP_CREATED);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless($user->super_admin, Response::HTTP_NOT_FOUND);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'admin_role' => ['sometimes', 'in:'.implode(',', self::ROLES)],
            'password' => ['sometimes', 'nullable', Password::defaults()],
        ]);

        $data = array_filter([
            'name' => $validated['name'] ?? null,
            'admin_role' => $validated['admin_role'] ?? null,
        ], fn ($v) => $v !== null);

        if (! empty($validated['password'])) {
            $data['password'] = $validated['password'];
        }

        $user->update($data);

        return response()->json(['data' => $this->serialize($user->refresh())]);
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

        $user->update(['super_admin' => false, 'admin_role' => null]);

        return response()->json(['message' => 'Acesso administrativo revogado com sucesso.']);
    }

    private function serialize(User $user): array
    {
        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'admin_role' => $user->admin_role ?? 'super_admin',
            'created_at' => $user->created_at?->toISOString(),
        ];
    }
}
