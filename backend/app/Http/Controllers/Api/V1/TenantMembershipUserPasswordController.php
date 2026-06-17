<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Auth\Models\Membership;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class TenantMembershipUserPasswordController extends Controller
{
    public function __invoke(Request $request, Membership $membership): JsonResponse
    {
        $data = $request->validate([
            'admin_password' => ['required', 'string'],
            'password'       => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $currentMembership = currentMembership();
        abort_if($currentMembership === null, Response::HTTP_FORBIDDEN);

        if (! Hash::check($data['admin_password'], $currentMembership->user->password)) {
            return response()->json([
                'message' => 'Sua senha está incorreta.',
                'errors'  => ['admin_password' => ['Sua senha está incorreta.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $membership->user->password = Hash::make($data['password']);
        $membership->user->save();

        return response()->json(['message' => 'Senha redefinida com sucesso.']);
    }
}
