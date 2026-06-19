<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Learning\Models\Playbook;
use App\Domains\Learning\Models\PlaybookStep;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaybookController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $category = $request->query('category');

        $playbooks = Playbook::query()
            ->withCount('steps')
            ->where('status', 'published')
            ->when($q !== '', fn ($query) => $query->where(function ($inner) use ($q): void {
                $inner->where('title', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            }))
            ->when($category, fn ($query) => $query->where('category', $category))
            ->orderBy('title')
            ->get();

        return response()->json([
            'data' => $playbooks->map(fn (Playbook $pb): array => $this->serialize($pb))->all(),
        ]);
    }

    public function show(Playbook $playbook): JsonResponse
    {
        abort_if($playbook->status !== 'published', 404);

        return response()->json(['data' => $this->serialize($playbook->load('steps'))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Playbook $playbook): array
    {
        return [
            'id'          => $playbook->getKey(),
            'title'       => $playbook->title,
            'description' => $playbook->description,
            'category'    => $playbook->category,
            'emoji'       => $playbook->emoji,
            'status'      => $playbook->status,
            'steps_count' => $playbook->steps_count ?? ($playbook->relationLoaded('steps') ? $playbook->steps->count() : 0),
            'created_at'  => $playbook->created_at?->toISOString(),
            'steps'       => $playbook->relationLoaded('steps')
                ? $playbook->steps->map(fn (PlaybookStep $step): array => [
                    'id'         => $step->getKey(),
                    'sort_order' => $step->sort_order,
                    'title'      => $step->title,
                    'content'    => $step->content,
                    'tip'        => $step->tip,
                    'tip_type'   => $step->tip_type,
                ])->all()
                : [],
        ];
    }
}
