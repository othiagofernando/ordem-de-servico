<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domains\Learning\Models\Playbook;
use App\Domains\Learning\Models\PlaybookStep;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AdminPlaybookController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $category = $request->query('category');
        $status = $request->query('status', 'all');

        $playbooks = Playbook::query()
            ->withCount('steps')
            ->when($q !== '', fn ($query) => $query->where(function ($inner) use ($q): void {
                $inner->where('title', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            }))
            ->when($category, fn ($query) => $query->where('category', $category))
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->orderBy('title')
            ->get();

        return response()->json([
            'data' => $playbooks->map(fn (Playbook $pb): array => $this->serialize($pb))->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validatePayload($request);

        $playbook = DB::transaction(function () use ($payload): Playbook {
            $playbook = Playbook::create([
                'title'       => $payload['title'],
                'description' => $payload['description'] ?? null,
                'category'    => $payload['category'] ?? null,
                'emoji'       => $payload['emoji'] ?? '📖',
                'status'      => $payload['status'],
            ]);

            $this->syncSteps($playbook, $payload['steps'] ?? []);

            return $playbook->load('steps');
        });

        return response()->json(['data' => $this->serialize($playbook)], Response::HTTP_CREATED);
    }

    public function show(Playbook $playbook): JsonResponse
    {
        return response()->json(['data' => $this->serialize($playbook->load('steps'))]);
    }

    public function update(Request $request, Playbook $playbook): JsonResponse
    {
        $payload = $this->validatePayload($request);

        $playbook = DB::transaction(function () use ($playbook, $payload): Playbook {
            $playbook->forceFill([
                'title'       => $payload['title'],
                'description' => $payload['description'] ?? null,
                'category'    => $payload['category'] ?? null,
                'emoji'       => $payload['emoji'] ?? '📖',
                'status'      => $payload['status'],
            ])->save();

            $this->syncSteps($playbook, $payload['steps'] ?? []);

            return $playbook->load('steps');
        });

        return response()->json(['data' => $this->serialize($playbook)]);
    }

    public function destroy(Playbook $playbook): JsonResponse
    {
        $playbook->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    private function syncSteps(Playbook $playbook, array $steps): void
    {
        $playbook->steps()->delete();
        foreach ($steps as $i => $step) {
            PlaybookStep::create([
                'playbook_id' => $playbook->getKey(),
                'sort_order'  => $i,
                'title'       => $step['title'],
                'content'     => $step['content'],
                'tip'         => $step['tip'] ?? null,
                'tip_type'    => $step['tip_type'] ?? 'info',
            ]);
        }
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'title'            => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string', 'max:500'],
            'category'         => ['nullable', 'string', 'max:100'],
            'emoji'            => ['nullable', 'string', 'max:10'],
            'status'           => ['required', 'string', 'in:draft,published'],
            'steps'            => ['required', 'array', 'min:1'],
            'steps.*.title'    => ['required', 'string', 'max:255'],
            'steps.*.content'  => ['required', 'string'],
            'steps.*.tip'      => ['nullable', 'string', 'max:500'],
            'steps.*.tip_type' => ['nullable', 'string', 'in:info,warning,success'],
        ]);
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
