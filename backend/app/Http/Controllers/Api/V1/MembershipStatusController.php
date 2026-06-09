<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Auth\Models\Membership;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class MembershipStatusController extends Controller
{
    /**
     * Update a tenant membership lifecycle status.
     *
     * @group Auth
     */
    public function __invoke(Request $request, Membership $membership): JsonResponse
    {
        abort_unless($membership->company_id === currentCompany()?->getKey(), Response::HTTP_NOT_FOUND);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in([
                Membership::STATUS_ACTIVE,
                Membership::STATUS_SUSPENDED,
            ])],
        ]);

        if ($membership->status === Membership::STATUS_INVITED && $data['status'] === Membership::STATUS_SUSPENDED) {
            return response()->json([
                'message' => 'Invited memberships must be accepted before they can be suspended.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $membership->forceFill([
            'status' => $data['status'],
        ])->save();

        $membership->load('user');

        return response()->json([
            'data' => [
                'id' => $membership->getKey(),
                'role' => $membership->role,
                'status' => $membership->status,
                'user_name' => $membership->user?->name,
                'user_email' => $membership->user?->email,
            ],
        ]);
    }
}
