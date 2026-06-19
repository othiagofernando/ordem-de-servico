<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\ServiceOrders\Models\ServiceOrderRating;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RatingsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'rating' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
        ]);

        $query = ServiceOrderRating::query()
            ->with(['serviceOrder:id,reference,order_number,title,customer_id,assigned_membership_id',
                    'serviceOrder.customer:id,name',
                    'serviceOrder.assignedMembership.user:id,name'])
            ->orderByDesc('rated_at');

        if ($request->filled('rating')) {
            $query->where('rating', $request->integer('rating'));
        }

        if ($request->filled('from')) {
            $query->whereDate('rated_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('rated_at', '<=', $request->input('to'));
        }

        $perPage = $request->integer('per_page', 20);
        $paginated = $query->paginate($perPage);

        $data = $paginated->getCollection()->map(fn (ServiceOrderRating $r) => [
            'id' => $r->getKey(),
            'rating' => $r->rating,
            'comment' => $r->comment,
            'rated_at' => $r->rated_at?->toIso8601String(),
            'service_order_id' => $r->service_order_id,
            'service_order_reference' => $r->serviceOrder?->reference,
            'service_order_order_number' => $r->serviceOrder?->order_number,
            'service_order_title' => $r->serviceOrder?->title,
            'customer_name' => $r->serviceOrder?->customer?->name,
            'technician_name' => $r->serviceOrder?->assignedMembership?->user?->name,
        ])->values()->all();

        $total = ServiceOrderRating::query()->count();
        $avgRating = ServiceOrderRating::query()->avg('rating');
        $distribution = ServiceOrderRating::query()
            ->selectRaw('rating, count(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
            'stats' => [
                'total_ratings' => $total,
                'average_rating' => $avgRating ? round((float) $avgRating, 2) : null,
                'distribution' => array_map('intval', $distribution),
            ],
        ]);
    }
}
