<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Companies\Actions\ResolveCompanyOperationalSettings;
use App\Domains\Companies\Models\CompanyOperationalSetting;
use App\Domains\Companies\Models\CompanySettingAudit;
use App\Domains\Companies\Support\CurrentCompanyContext;
use App\Domains\Companies\Support\FrontendTenantUrl;
use App\Http\Controllers\Controller;
use App\Rules\BrazilianDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'trade_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'min:3',
                'max:64',
                'regex:/^[a-z0-9-]+$/',
                Rule::notIn(['admin', 'api', 'www', 'portal', 'login', 'cadastro']),
                Rule::unique('companies', 'slug')->ignore($company->getKey()),
            ],
            'document' => ['sometimes', 'nullable', 'string', 'max:32', new BrazilianDocument()],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address_line_1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address_line_2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'district' => ['sometimes', 'nullable', 'string', 'max:150'],
            'city' => ['sometimes', 'nullable', 'string', 'max:150'],
            'state' => ['sometimes', 'nullable', 'string', 'size:2'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'brand_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'customer_contact_line' => ['sometimes', 'nullable', 'string', 'max:160'],
            'service_order_reference_prefix' => ['sometimes', 'nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/'],
            'portal_intro_text' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'portal_footer_text' => ['sometimes', 'nullable', 'string', 'max:500'],
            'portal_approved_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'portal_rejected_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'document_footer_text' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'intake_checklists_enabled' => ['sometimes', 'boolean'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'default_quote_validity_days' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'block_delivery_with_outstanding_balance' => ['sometimes', 'boolean'],
            'notify_quote_available' => ['sometimes', 'boolean'],
            'notify_quote_available_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notify_quote_expiring' => ['sometimes', 'boolean'],
            'notify_quote_expiring_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notify_ready_for_pickup' => ['sometimes', 'boolean'],
            'notify_ready_for_pickup_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notify_payment_receipt' => ['sometimes', 'boolean'],
            'notify_payment_receipt_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notification_footer_text' => ['sometimes', 'nullable', 'string', 'max:500'],
            'passwordless_code_enabled' => ['sometimes', 'boolean'],
            'magic_link_enabled' => ['sometimes', 'boolean'],
        ]);

        $authKeys = ['passwordless_code_enabled', 'magic_link_enabled'];
        $companyKeys = [
            'name',
            'trade_name',
            'slug',
            'document',
            'email',
            'phone',
            'address_line_1',
            'address_line_2',
            'district',
            'city',
            'state',
            'postal_code',
            'website',
        ];
        $authPayload = collect($payload)->only($authKeys)->all();
        $companyPayload = collect($payload)->only($companyKeys)->all();
        $settingsPayload = collect($payload)->except([...$authKeys, ...$companyKeys])->all();

        if (array_key_exists('service_order_reference_prefix', $settingsPayload)) {
            $settingsPayload['service_order_reference_prefix'] = filled($settingsPayload['service_order_reference_prefix'] ?? null)
                ? str($settingsPayload['service_order_reference_prefix'])->trim()->upper()->value()
                : null;
        }

        if (array_key_exists('state', $companyPayload) && filled($companyPayload['state'] ?? null)) {
            $companyPayload['state'] = str($companyPayload['state'])->upper()->value();
        }

        if (array_key_exists('currency', $settingsPayload) && filled($settingsPayload['currency'] ?? null)) {
            $settingsPayload['currency'] = str($settingsPayload['currency'])->upper()->value();
        }

        if ($companyPayload !== []) {
            $company->update($companyPayload);
        }

        if ($authPayload !== []) {
            $company->update($authPayload);
        }

        $settings = CompanyOperationalSetting::query()
            ->withoutGlobalScopes()
            ->firstOrNew(['company_id' => $company->getKey()]);

        $before = $resolveCompanyOperationalSettings->handle($company);
        $beforeValues = $this->rawSettingValues($before);

        $settings->forceFill($settingsPayload);
        $settings->company_id = $company->getKey();
        $settings->save();

        $resolved = $resolveCompanyOperationalSettings->handle($company->fresh());
        $afterValues = $this->rawSettingValues($resolved);
        $changedKeys = collect(array_keys($settingsPayload))
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
            'profile' => [
                'name' => $company->name,
                'slug' => $company->slug,
                'trade_name' => $company->trade_name,
                'document' => $company->document,
                'email' => $company->email,
                'phone' => $company->phone,
                'website' => $company->website,
                'tenant_url' => app(FrontendTenantUrl::class)->base($company),
                'address' => [
                    'line_1' => $company->address_line_1,
                    'line_2' => $company->address_line_2,
                    'district' => $company->district,
                    'city' => $company->city,
                    'state' => $company->state,
                    'postal_code' => $company->postal_code,
                ],
                'logo_url' => $company->logo_path ? Storage::disk('public')->url($company->logo_path) : null,
            ],
            'branding' => [
                'brand_name' => $settings->brandName($company),
                'customer_contact_line' => $settings->customer_contact_line,
            ],
            'service_orders' => [
                'reference_prefix' => $settings->serviceOrderReferencePrefix(),
            ],
            'portal' => [
                'intro_text' => $settings->portal_intro_text,
                'footer_text' => $settings->portal_footer_text,
                'approved_text' => $settings->portalApprovedText(),
                'rejected_text' => $settings->portalRejectedText(),
            ],
            'quotes' => [
                'default_validity_days' => $settings->default_quote_validity_days,
            ],
            'documents' => [
                'footer_text' => $settings->document_footer_text,
            ],
            'intake' => [
                'checklists_enabled' => $settings->intake_checklists_enabled,
            ],
            'finance' => [
                'block_delivery_with_outstanding_balance' => $settings->block_delivery_with_outstanding_balance,
                'currency' => $settings->currency,
            ],
            'communications' => [
                'notification_footer_text' => $settings->notification_footer_text,
            ],
            'notifications' => [
                'quote_available' => $settings->notify_quote_available,
                'quote_available_text' => $settings->notify_quote_available_text,
                'quote_expiring' => $settings->notify_quote_expiring,
                'quote_expiring_text' => $settings->notify_quote_expiring_text,
                'ready_for_pickup' => $settings->notify_ready_for_pickup,
                'ready_for_pickup_text' => $settings->notify_ready_for_pickup_text,
                'payment_receipt' => $settings->notify_payment_receipt,
                'payment_receipt_text' => $settings->notify_payment_receipt_text,
            ],
            'auth' => [
                'passwordless_code_enabled' => (bool) $company->passwordless_code_enabled,
                'magic_link_enabled' => (bool) $company->magic_link_enabled,
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
            'customer_contact_line' => $settings->customer_contact_line,
            'service_order_reference_prefix' => $settings->service_order_reference_prefix,
            'portal_intro_text' => $settings->portal_intro_text,
            'portal_footer_text' => $settings->portal_footer_text,
            'portal_approved_text' => $settings->portal_approved_text,
            'portal_rejected_text' => $settings->portal_rejected_text,
            'document_footer_text' => $settings->document_footer_text,
            'intake_checklists_enabled' => $settings->intake_checklists_enabled,
            'currency' => $settings->currency,
            'default_quote_validity_days' => $settings->default_quote_validity_days,
            'block_delivery_with_outstanding_balance' => $settings->block_delivery_with_outstanding_balance,
            'notify_quote_available' => $settings->notify_quote_available,
            'notify_quote_available_text' => $settings->notify_quote_available_text,
            'notify_quote_expiring' => $settings->notify_quote_expiring,
            'notify_quote_expiring_text' => $settings->notify_quote_expiring_text,
            'notify_ready_for_pickup' => $settings->notify_ready_for_pickup,
            'notify_ready_for_pickup_text' => $settings->notify_ready_for_pickup_text,
            'notify_payment_receipt' => $settings->notify_payment_receipt,
            'notify_payment_receipt_text' => $settings->notify_payment_receipt_text,
            'notification_footer_text' => $settings->notification_footer_text,
        ];
    }
}
