<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\ServiceOrders\Models\QuoteRevision;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Domains\ServiceOrders\Models\ServiceOrderRating;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PortalRatingController extends Controller
{
    public function store(Request $request, string $token): JsonResponse
    {
        $quote = QuoteRevision::query()
            ->where('portal_token_hash', hash('sha256', $token))
            ->with(['serviceOrder'])
            ->first();

        if (! $quote) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $serviceOrder = $quote->serviceOrder;

        if ($serviceOrder->status !== ServiceOrder::STATUS_DELIVERED) {
            return response()->json([
                'message' => 'A avaliação só está disponível após a entrega.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $rating = ServiceOrderRating::updateOrCreate(
            ['service_order_id' => $serviceOrder->getKey()],
            [
                'company_id' => $serviceOrder->company_id,
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? null,
                'rated_at' => now(),
            ],
        );

        return response()->json([
            'data' => [
                'id' => $rating->getKey(),
                'rating' => $rating->rating,
                'comment' => $rating->comment,
                'rated_at' => $rating->rated_at?->toIso8601String(),
            ],
        ], Response::HTTP_CREATED);
    }
}
