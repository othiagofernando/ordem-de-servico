<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Auth\Models\Membership;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class TenantMembershipDeleteController extends Controller
{
    public function __invoke(Request $request, Membership $membership): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $currentMembership = currentMembership();
        abort_if($currentMembership === null, Response::HTTP_FORBIDDEN);

        if ($currentMembership->id === $membership->id) {
            return response()->json(
                ['message' => 'Você não pode remover a si mesmo.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $user = $currentMembership->user;
        if (! Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => 'Senha incorreta.',
                'errors' => ['password' => ['Senha incorreta.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $membership->delete();

        return response()->json(['message' => 'Membro removido com sucesso.']);
    }
}
