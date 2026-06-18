<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Equipments\Models\Equipment;
use App\Domains\Equipments\Models\EquipmentEvidence;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EquipmentEvidenceController extends Controller
{
    /**
     * List visual evidences for a tenant-scoped equipment record.
     *
     * @group Equipments
     */
    public function index(Equipment $equipment): JsonResponse
    {
        abort_unless($equipment->company_id === currentCompany()?->getKey(), Response::HTTP_NOT_FOUND);

        $equipment->loadMissing('evidences');

        return response()->json([
            'data' => $equipment->evidences->map(fn (EquipmentEvidence $e): array => $this->serialize($e))->all(),
        ]);
    }

    /**
     * Upload a visual evidence file to a tenant-scoped equipment record.
     *
     * @group Equipments
     */
    public function store(Request $request, Equipment $equipment): JsonResponse
    {
        abort_unless($equipment->company_id === currentCompany()?->getKey(), Response::HTTP_NOT_FOUND);

        $payload = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'caption' => ['nullable', 'string', 'max:500'],
        ]);

        $uploadedFile = $request->file('file');
        $extension = $uploadedFile->getClientOriginalExtension();
        $storageKey = sprintf(
            '%d/equipment/%d/%s%s',
            $equipment->company_id,
            $equipment->getKey(),
            Str::uuid()->toString(),
            $extension !== '' ? '.'.$extension : '',
        );

        $uploadedFile->storeAs(dirname($storageKey), basename($storageKey), ['disk' => 'local']);

        $evidence = EquipmentEvidence::query()->create([
            'company_id' => $equipment->company_id,
            'equipment_id' => $equipment->getKey(),
            'uploaded_by_membership_id' => currentMembership()?->getKey(),
            'file_name' => $uploadedFile->getClientOriginalName(),
            'storage_key' => $storageKey,
            'mime_type' => $uploadedFile->getMimeType() ?? $uploadedFile->getClientMimeType(),
            'file_size_bytes' => $uploadedFile->getSize(),
            'caption' => $payload['caption'] ?? null,
        ]);

        return response()->json([
            'data' => $this->serialize($evidence),
        ], Response::HTTP_CREATED);
    }

    /**
     * Download a visual evidence file for a tenant-scoped equipment record.
     *
     * @group Equipments
     */
    public function download(Equipment $equipment, EquipmentEvidence $evidence): StreamedResponse
    {
        abort_unless($equipment->company_id === currentCompany()?->getKey(), Response::HTTP_NOT_FOUND);

        abort_if(
            $evidence->equipment_id !== $equipment->getKey() || $evidence->company_id !== $equipment->company_id,
            Response::HTTP_NOT_FOUND,
        );

        abort_unless(
            Storage::disk('local')->exists($evidence->storage_key),
            Response::HTTP_NOT_FOUND,
        );

        return Storage::disk('local')->download($evidence->storage_key, $evidence->file_name);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(EquipmentEvidence $e): array
    {
        return [
            'id' => $e->getKey(),
            'equipment_id' => $e->equipment_id,
            'file_name' => $e->file_name,
            'mime_type' => $e->mime_type,
            'file_size_bytes' => $e->file_size_bytes,
            'caption' => $e->caption,
            'uploaded_by_membership_id' => $e->uploaded_by_membership_id,
            'created_at' => $e->created_at?->toIso8601String(),
        ];
    }
}
