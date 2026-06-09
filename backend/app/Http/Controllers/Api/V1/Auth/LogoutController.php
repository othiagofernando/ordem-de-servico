<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Companies\Support\CurrentCompanyContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class LogoutController extends Controller
{
    /**
     * Revoke the current API token.
     *
     * @group Auth
     */
    public function __invoke(CurrentCompanyContext $context): JsonResponse
    {
        $apiToken = $context->apiToken();

        if ($apiToken !== null) {
            $apiToken->delete();
        }

        return response()->json(['message' => 'Logged out.'], Response::HTTP_OK);
    }
}
