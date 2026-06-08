<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\ServiceOrders\Actions\CreateIntakeChecklistTemplateVersion;
use App\Domains\ServiceOrders\Models\IntakeChecklistTemplate;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntakeChecklistTemplateController extends Controller
{
    /**
     * List versioned intake checklist templates, optionally filtered by equipment category.
     *
     * @group Service Orders
     */
    public function index(Request $request): JsonResponse
    {
        $category = trim((string) $request->query('category', ''));

        $templates = IntakeChecklistTemplate::query()
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->orderBy('category')
            ->orderByDesc('version')
            ->get();

        return response()->json([
            'data' => $templates->map(fn (IntakeChecklistTemplate $template): array => $this->serializeTemplate($template))->all(),
        ]);
    }

    /**
     * Create a new immutable checklist template version for an equipment category.
     *
     * @group Service Orders
     */
    public function store(Request $request, CreateIntakeChecklistTemplateVersion $createTemplateVersion): JsonResponse
    {
        $payload = $request->validate([
            'category' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.key' => ['required', 'string', 'max:100'],
            'items.*.label' => ['required', 'string', 'max:255'],
            'items.*.type' => ['required', 'string', 'max:30'],
            'items.*.required' => ['sometimes', 'boolean'],
        ]);

        $template = $createTemplateVersion->handle(
            category: $payload['category'],
            title: $payload['title'],
            items: $payload['items'],
        );

        return response()->json([
            'data' => $this->serializeTemplate($template),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeTemplate(IntakeChecklistTemplate $template): array
    {
        return [
            'id' => $template->getKey(),
            'category' => $template->category,
            'version' => $template->version,
            'title' => $template->title,
            'items' => $template->items,
        ];
    }
}
