<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Auth\Models\Membership;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantMembershipUserUpdateController extends Controller
{
    public function __invoke(Request $request, Membership $membership): JsonResponse
    {
        $data = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($membership->user_id)],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $membership->user->update($data);

        return response()->json([
            'message' => 'Dados atualizados com sucesso.',
            'data' => [
                'user_name'  => $membership->user->name,
                'user_email' => $membership->user->email,
                'user_phone' => $membership->user->phone,
            ],
        ]);
    }
}
