<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * Report the application health for the bootstrapped API surface.
     *
     * @group Platform
     * @subgroup Foundation
     *
     * Returns a successful health payload when the API is bootable.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
        ]);
    }
}
