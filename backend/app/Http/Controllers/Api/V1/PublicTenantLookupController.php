<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Companies\Models\Company;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PublicTenantLookupController extends Controller
{
    public function __invoke(string $slug): JsonResponse
    {
        $company = Company::query()
            ->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->where('status', Company::STATUS_ACTIVE)
            ->where('slug', $slug)
            ->first();

        if ($company === null) {
            return response()->json([
                'message' => 'Empresa não encontrada.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'data' => [
                'id' => $company->getKey(),
                'uuid' => $company->uuid,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
        ]);
    }
}
