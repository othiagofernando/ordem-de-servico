<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CepLookupService;
use Illuminate\Http\JsonResponse;

class CepLookupController extends Controller
{
    public function __invoke(string $cep): JsonResponse
    {
        $data = CepLookupService::search($cep);

        if ($data === null) {
            return response()->json(null);
        }

        return response()->json([
            'street' => $data['street'] ?? '',
            'district' => $data['neighborhood'] ?? '',
            'city' => $data['city'] ?? '',
            'state' => $data['state'] ?? '',
        ]);
    }
}
