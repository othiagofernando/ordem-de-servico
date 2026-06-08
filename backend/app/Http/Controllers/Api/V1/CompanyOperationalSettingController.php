<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Companies\Actions\ResolveCompanyOperationalSettings;
use App\Domains\Companies\Models\CompanyOperationalSetting;
use App\Domains\Companies\Models\CompanySettingAudit;
use App\Domains\Companies\Support\CurrentCompanyContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CompanyOperationalSettingController extends Controller
{
    /**
     * View the active company's operational settings.
     *
     * @group Company Settings
     */
    public function show(
        CurrentCompanyContext $context,
        ResolveCompanyOperationalSettings $resolveCompanyOperationalSettings,
    ): JsonResponse {
        $company = $context->company();

        abort_if($company === null, Response::HTTP_FORBIDDEN, 'Missing active company context.');

        $settings = $resolveCompanyOperationalSettings->handle($company);

        return response()->json([
            'data' => $this->serializeSettings($company, $settings),
        ]);
    }

    /**
     * Update the active company's operational settings.
     *
     * @group Company Settings
     */
    public function update(
        Request $request,
        CurrentCompanyContext $context,
        ResolveCompanyOperationalSettings $resolveCompanyOperationalSettings,
    ): JsonResponse {
        $company = $context->company();
        $membership = $context->membership();

        abort_if($company === null, Response::HTTP_FORBIDDEN, 'Missing active company context.');
        abort_if($membership === null, Response::HTTP_FORBIDDEN, 'Missing active membership context.');

        $payload = $request->validate([
            'brand_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'portal_intro_text' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'default_quote_validity_days' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'notify_quote_available' => ['sometimes', 'boolean'],
            'notify_quote_expiring' => ['sometimes', 'boolean'],
            'notify_ready_for_pickup' => ['sometimes', 'boolean'],
            'notify_payment_receipt' => ['sometimes', 'boolean'],
        ]);

        $settings = CompanyOperationalSetting::query()
            ->withoutGlobalScopes()
            ->firstOrNew(['company_id' => $company->getKey()]);

        $before = $resolveCompanyOperationalSettings->handle($company);
        $beforeValues = $this->rawSettingValues($before);

        $settings->forceFill($payload);
        $settings->company_id = $company->getKey();
        $settings->save();

        $resolved = $resolveCompanyOperationalSettings->handle($company->fresh());
        $afterValues = $this->rawSettingValues($resolved);
        $changedKeys = collect(array_keys($payload))
            ->filter(fn (string $key): bool => ($beforeValues[$key] ?? null) !== ($afterValues[$key] ?? null))
            ->values()
            ->all();

        if ($changedKeys !== []) {
            CompanySettingAudit::query()
                ->withoutGlobalScopes()
                ->create([
                    'company_id' => $company->getKey(),
                    'actor_membership_id' => $membership->getKey(),
                    'changed_keys' => $changedKeys,
                    'before_values' => collect($beforeValues)->only($changedKeys)->all(),
                    'after_values' => collect($afterValues)->only($changedKeys)->all(),
                ]);
        }

        return response()->json([
            'data' => $this->serializeSettings($company->fresh(), $resolved),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeSettings($company, CompanyOperationalSetting $settings): array
    {
        return [
            'company_id' => $company->getKey(),
            'branding' => [
                'brand_name' => $settings->brandName($company),
            ],
            'portal' => [
                'intro_text' => $settings->portal_intro_text,
            ],
            'quotes' => [
                'default_validity_days' => $settings->default_quote_validity_days,
            ],
            'notifications' => [
                'quote_available' => $settings->notify_quote_available,
                'quote_expiring' => $settings->notify_quote_expiring,
                'ready_for_pickup' => $settings->notify_ready_for_pickup,
                'payment_receipt' => $settings->notify_payment_receipt,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rawSettingValues(CompanyOperationalSetting $settings): array
    {
        return [
            'brand_name' => $settings->brand_name,
            'portal_intro_text' => $settings->portal_intro_text,
            'default_quote_validity_days' => $settings->default_quote_validity_days,
            'notify_quote_available' => $settings->notify_quote_available,
            'notify_quote_expiring' => $settings->notify_quote_expiring,
            'notify_ready_for_pickup' => $settings->notify_ready_for_pickup,
            'notify_payment_receipt' => $settings->notify_payment_receipt,
        ];
    }
}
