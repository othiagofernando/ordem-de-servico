<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Companies\Support\CurrentCompanyContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class TenantContextController extends Controller
{
    /**
     * Return the authenticated active company context.
     *
     * @group Tenant
     *
     * Returns the company, membership, and user resolved from the bearer token.
     */
    public function __invoke(CurrentCompanyContext $context): JsonResponse
    {
        return response()->json([
            'company' => [
                'id' => $context->company()?->getKey(),
                'uuid' => $context->company()?->uuid,
                'name' => $context->company()?->name,
            ],
            'membership' => [
                'id' => $context->membership()?->getKey(),
                'role' => $context->membership()?->role,
                'status' => $context->membership()?->status,
            ],
            'user' => [
                'id' => $context->user()?->getKey(),
                'name' => $context->user()?->name,
                'email' => $context->user()?->email,
            ],
        ]);
    }
}
