<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class ChangePasswordController extends Controller
{
    /**
     * Change the authenticated user's password.
     *
     * @group Auth
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = currentMembership()?->user;
        abort_if($user === null, Response::HTTP_FORBIDDEN);

        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Senha atual incorreta.',
                'errors' => ['current_password' => ['Senha atual incorreta.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        return response()->json(['message' => 'Senha alterada com sucesso.']);
    }
}
