<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Parts\Models\Part;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class PartController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20), 100);

        $query = Part::query()->orderBy('name');

        if (filled($request->query('q'))) {
            $q = $request->query('q');
            $query->where(function ($builder) use ($q): void {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%")
                    ->orWhere('category', 'like', "%{$q}%");
            });
        }

        if (filled($request->query('category'))) {
            $query->where('category', $request->query('category'));
        }

        if ($request->boolean('low_stock')) {
            $query->whereRaw('min_stock_quantity > 0')
                ->whereRaw('stock_quantity <= min_stock_quantity');
        }

        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        } else {
            $query->where('active', true);
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())->map(fn (Part $part): array => $this->serialize($part))->all(),
            'meta' => [
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'from'         => $paginator->firstItem() ?? 0,
                'to'           => $paginator->lastItem() ?? 0,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'               => ['required', 'string', 'max:200'],
            'description'        => ['nullable', 'string'],
            'category'           => ['nullable', 'string', 'max:100'],
            'sku'                => ['nullable', 'string', 'max:100', Rule::unique('parts', 'sku')->where('company_id', currentCompany()->getKey())],
            'unit'               => ['nullable', 'string', Rule::in(['un', 'par', 'm', 'cm', 'g', 'ml', 'kg', 'l'])],
            'stock_quantity'     => ['nullable', 'numeric', 'min:0'],
            'min_stock_quantity' => ['nullable', 'numeric', 'min:0'],
            'cost_price_cents'   => ['nullable', 'integer', 'min:0'],
            'sale_price_cents'   => ['nullable', 'integer', 'min:0'],
            'active'             => ['nullable', 'boolean'],
        ]);

        $part = Part::query()->create($data);

        return response()->json(['data' => $this->serialize($part)], Response::HTTP_CREATED);
    }

    public function show(Part $part): JsonResponse
    {
        return response()->json(['data' => $this->serialize($part)]);
    }

    public function update(Request $request, Part $part): JsonResponse
    {
        $data = $request->validate([
            'name'               => ['required', 'string', 'max:200'],
            'description'        => ['nullable', 'string'],
            'category'           => ['nullable', 'string', 'max:100'],
            'sku'                => ['nullable', 'string', 'max:100', Rule::unique('parts', 'sku')->where('company_id', currentCompany()->getKey())->ignore($part->getKey())],
            'unit'               => ['nullable', 'string', Rule::in(['un', 'par', 'm', 'cm', 'g', 'ml', 'kg', 'l'])],
            'stock_quantity'     => ['nullable', 'numeric', 'min:0'],
            'min_stock_quantity' => ['nullable', 'numeric', 'min:0'],
            'cost_price_cents'   => ['nullable', 'integer', 'min:0'],
            'sale_price_cents'   => ['nullable', 'integer', 'min:0'],
            'active'             => ['nullable', 'boolean'],
        ]);

        $part->update($data);

        return response()->json(['data' => $this->serialize($part->fresh())]);
    }

    public function destroy(Part $part): JsonResponse
    {
        if ($part->serviceOrderParts()->exists()) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Peça está em uso em ordens de serviço e não pode ser excluída.');
        }

        $part->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function adjustStock(Request $request, Part $part): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'numeric'],
            'notes'    => ['nullable', 'string', 'max:500'],
        ]);

        $newStock = (float) $part->stock_quantity + (float) $data['quantity'];

        if ($newStock < 0) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Estoque insuficiente.');
        }

        $part->update(['stock_quantity' => $newStock]);

        return response()->json(['data' => $this->serialize($part->fresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(Part $part): array
    {
        return [
            'id'                  => $part->getKey(),
            'name'                => $part->name,
            'description'         => $part->description,
            'category'            => $part->category,
            'sku'                 => $part->sku,
            'unit'                => $part->unit,
            'stock_quantity'      => (float) $part->stock_quantity,
            'min_stock_quantity'  => (float) $part->min_stock_quantity,
            'cost_price_cents'    => $part->cost_price_cents,
            'sale_price_cents'    => $part->sale_price_cents,
            'active'              => $part->active,
            'is_low_stock'        => $part->isLowStock(),
            'created_at'          => $part->created_at?->toIso8601String(),
        ];
    }
}
