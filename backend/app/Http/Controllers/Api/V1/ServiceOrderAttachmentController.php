<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Auditing\Actions\RecordBusinessAudit;
use App\Domains\ServiceOrders\Actions\RecordServiceOrderTimelineEvent;
use App\Domains\ServiceOrders\Actions\ResolveVisibleServiceOrder;
use App\Domains\ServiceOrders\Actions\VisibleServiceOrders;
use App\Domains\ServiceOrders\Models\ServiceOrder;
use App\Domains\ServiceOrders\Models\ServiceOrderAttachment;
use App\Domains\ServiceOrders\Models\ServiceOrderTimelineEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServiceOrderAttachmentController extends Controller
{
    /**
     * List typed attachments for a visible service order.
     *
     * @group Service Orders
     */
    public function index(
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
        ResolveVisibleServiceOrder $resolveVisibleServiceOrder,
    ): JsonResponse
    {
        $visible = $resolveVisibleServiceOrder->handle($serviceOrder, $visibleServiceOrders)->loadMissing('attachments');

        return response()->json([
            'data' => $visible->attachments->map(fn (ServiceOrderAttachment $a): array => $this->serialize($a))->all(),
        ]);
    }

    /**
     * Upload a typed attachment to a service order.
     *
     * @group Service Orders
     */
    public function store(
        Request $request,
        ServiceOrder $serviceOrder,
        VisibleServiceOrders $visibleServiceOrders,
        RecordServiceOrderTimelineEvent $recordTimelineEvent,
        RecordBusinessAudit $recordBusinessAudit,
        ResolveVisibleServiceOrder $resolveVisibleServiceOrder,
    ): JsonResponse {
        $visible = $resolveVisibleServiceOrder->handle($serviceOrder, $visibleServiceOrders)->loadMissing('attachments');
        $membership = currentMembership();

        $payload = $request->validate([
            'attachment_type' => ['required', 'string', 'in:'.implode(',', ServiceOrderAttachment::ALLOWED_TYPES)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'file' => ['required', 'file', 'max:20480'],
        ]);

        $uploadedFile = $request->file('file');
        $extension = $uploadedFile->getClientOriginalExtension();
        $storageKey = sprintf(
            '%d/service-orders/%d/%s%s',
            $visible->company_id,
            $visible->getKey(),
            Str::uuid()->toString(),
            $extension !== '' ? '.'.$extension : '',
        );

        $uploadedFile->storeAs(dirname($storageKey), basename($storageKey), ['disk' => 'local']);

        $attachment = ServiceOrderAttachment::query()->create([
            'company_id' => $visible->company_id,
            'service_order_id' => $visible->getKey(),
            'uploaded_by_membership_id' => $membership->getKey(),
            'attachment_type' => $payload['attachment_type'],
            'file_name' => $uploadedFile->getClientOriginalName(),
            'storage_key' => $storageKey,
            'mime_type' => $uploadedFile->getMimeType() ?? $uploadedFile->getClientMimeType(),
            'file_size_bytes' => $uploadedFile->getSize(),
            'notes' => $payload['notes'] ?? null,
        ]);

        $isSignedServiceOrderCopy = $attachment->attachment_type === ServiceOrderAttachment::TYPE_SIGNED_SERVICE_ORDER_COPY;

        $recordTimelineEvent->handle(
            $visible,
            type: $isSignedServiceOrderCopy ? 'signed_service_order_copy_uploaded' : 'attachment_added',
            title: $isSignedServiceOrderCopy ? 'Signed service order copy uploaded' : 'Attachment added',
            visibility: ServiceOrderTimelineEvent::VISIBILITY_INTERNAL,
            actorMembership: $membership,
            payload: [
                'attachment_id' => $attachment->getKey(),
                'attachment_type' => $attachment->attachment_type,
                'file_name' => $attachment->file_name,
            ],
        );

        if ($isSignedServiceOrderCopy) {
            $recordBusinessAudit->handle(
                $visible->company_id,
                'document',
                'service_order_signed_copy_uploaded',
                auditable: $visible,
                actorMembership: $membership,
                context: [
                    'service_order_id' => $visible->getKey(),
                    'attachment_id' => $attachment->getKey(),
                    'attachment_type' => $attachment->attachment_type,
                ],
                afterValues: [
                    'file_name' => $attachment->file_name,
                    'mime_type' => $attachment->mime_type,
                    'file_size_bytes' => $attachment->file_size_bytes,
                    'notes' => $attachment->notes,
                ],
            );
        }

        return response()->json([
            'data' => $this->serialize($attachment),
        ], Response::HTTP_CREATED);
    }

    /**
     * Download an attachment through the authorized API (no public URL).
     *
     * @group Service Orders
     */
    public function download(
        ServiceOrder $serviceOrder,
        ServiceOrderAttachment $attachment,
        VisibleServiceOrders $visibleServiceOrders,
        ResolveVisibleServiceOrder $resolveVisibleServiceOrder,
    ): StreamedResponse {
        $visible = $resolveVisibleServiceOrder->handle($serviceOrder, $visibleServiceOrders)->loadMissing('attachments');

        abort_if(
            $attachment->service_order_id !== $visible->getKey() || $attachment->company_id !== $visible->company_id,
            Response::HTTP_NOT_FOUND,
        );

        abort_unless(
            Storage::disk('local')->exists($attachment->storage_key),
            Response::HTTP_NOT_FOUND,
        );

        return Storage::disk('local')->download($attachment->storage_key, $attachment->file_name);
    }
    /**
     * @return array<string, mixed>
     */
    protected function serialize(ServiceOrderAttachment $attachment): array
    {
        return [
            'id' => $attachment->getKey(),
            'attachment_type' => $attachment->attachment_type,
            'file_name' => $attachment->file_name,
            'mime_type' => $attachment->mime_type,
            'file_size_bytes' => $attachment->file_size_bytes,
            'notes' => $attachment->notes,
            'uploaded_by_membership_id' => $attachment->uploaded_by_membership_id,
            'created_at' => $attachment->created_at?->toIso8601String(),
        ];
    }
}
