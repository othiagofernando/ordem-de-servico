<?php

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MembershipInvitationAcceptController;
use App\Http\Controllers\Api\V1\Auth\MembershipInvitationShowController;
use App\Http\Controllers\Api\V1\Auth\PasswordlessCodeRequestController;
use App\Http\Controllers\Api\V1\Auth\PasswordlessCodeVerifyController;
use App\Http\Controllers\Api\V1\Auth\PasswordlessCompanyLookupController;
use App\Http\Controllers\Api\V1\Auth\PasswordlessMagicLinkConsumeController;
use App\Http\Controllers\Api\V1\Auth\PasswordlessMagicLinkRequestController;
use App\Http\Controllers\Api\V1\Auth\PermissionProfileController;
use App\Http\Controllers\Api\V1\Auth\BootstrapController;
use App\Http\Controllers\Api\V1\Auth\SelectCompanyController;
use App\Http\Controllers\Api\V1\BusinessAuditController;
use App\Http\Controllers\Api\V1\CompanyOperationalSettingController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\CustomerQuotePortalController;
use App\Http\Controllers\Api\V1\EquipmentController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\IntakeChecklistTemplateController;
use App\Http\Controllers\Api\V1\MembershipAuthorizationController;
use App\Http\Controllers\Api\V1\MembershipStatusController;
use App\Http\Controllers\Api\V1\QuotePortalTokenController;
use App\Http\Controllers\Api\V1\QuoteRevisionController;
use App\Http\Controllers\Api\V1\ServiceOrderAttachmentController;
use App\Http\Controllers\Api\V1\ServiceOrderClosureController;
use App\Http\Controllers\Api\V1\ServiceOrderController;
use App\Http\Controllers\Api\V1\ServiceOrderExecutionController;
use App\Http\Controllers\Api\V1\ServiceOrderFinancialController;
use App\Http\Controllers\Api\V1\ServiceOrderWarrantyController;
use App\Http\Controllers\Api\V1\ServiceOrderAssignmentController;
use App\Http\Controllers\Api\V1\TenantContextController;
use App\Http\Controllers\Api\V1\TenantMembershipController;
use App\Http\Controllers\Api\V1\TenantMembershipInvitationController;
use App\Http\Controllers\Api\V1\TenantMembershipListController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('api.v1.health');

Route::prefix('auth')->group(function (): void {
    Route::post('/login', LoginController::class)->name('api.v1.auth.login');
    Route::post('/select-company', SelectCompanyController::class)->name('api.v1.auth.select-company');
    Route::get('/invitations/{token}', MembershipInvitationShowController::class)->name('api.v1.auth.invitations.show');
    Route::post('/invitations/{token}/accept', MembershipInvitationAcceptController::class)
        ->name('api.v1.auth.invitations.accept');
    Route::post('/passwordless/code/request', PasswordlessCodeRequestController::class)
        ->name('api.v1.auth.passwordless.code.request');
    Route::post('/passwordless/code/verify', PasswordlessCodeVerifyController::class)
        ->name('api.v1.auth.passwordless.code.verify');
    Route::post('/passwordless/magic-link/request', PasswordlessMagicLinkRequestController::class)
        ->name('api.v1.auth.passwordless.magic-link.request');
    Route::post('/passwordless/magic-link/consume', PasswordlessMagicLinkConsumeController::class)
        ->name('api.v1.auth.passwordless.magic-link.consume');
    Route::post('/passwordless/lookup', PasswordlessCompanyLookupController::class)
        ->name('api.v1.auth.passwordless.lookup');
});

Route::prefix('portal')->group(function (): void {
    Route::get('/quotes/{token}', [CustomerQuotePortalController::class, 'show'])
        ->name('api.v1.portal.quotes.show');
    Route::post('/quotes/{token}/decision', [CustomerQuotePortalController::class, 'decide'])
        ->name('api.v1.portal.quotes.decision');
});

Route::middleware('auth.token')->group(function (): void {
    Route::post('/auth/logout', LogoutController::class)->name('api.v1.auth.logout');
    Route::get('/auth/bootstrap', BootstrapController::class)->name('api.v1.auth.bootstrap');
    Route::get('/auth/permissions/me', PermissionProfileController::class)->name('api.v1.auth.permissions.me');
    Route::get('/audit-logs', BusinessAuditController::class)
        ->middleware('permission:company_settings.manage')
        ->name('api.v1.audit-logs.index');
    Route::get('/tenant/context', TenantContextController::class)->name('api.v1.tenant.context');
    Route::get('/company/settings', [CompanyOperationalSettingController::class, 'show'])
        ->middleware('permission:company_settings.manage')
        ->name('api.v1.company.settings.show');
    Route::put('/company/settings', [CompanyOperationalSettingController::class, 'update'])
        ->middleware('permission:company_settings.manage')
        ->name('api.v1.company.settings.update');
    Route::get('/tenant/memberships', TenantMembershipListController::class)
        ->name('api.v1.tenant.memberships.index');
    Route::get('/tenant/memberships/{membership}', TenantMembershipController::class)
        ->name('api.v1.tenant.memberships.show');
    Route::post('/tenant/memberships/invitations', TenantMembershipInvitationController::class)
        ->middleware('permission:memberships.manage')
        ->name('api.v1.tenant.memberships.invitations.store');
    Route::patch('/tenant/memberships/{membership}/authorization', MembershipAuthorizationController::class)
        ->middleware('permission:memberships.manage')
        ->name('api.v1.tenant.memberships.authorization.update');
    Route::patch('/tenant/memberships/{membership}/status', MembershipStatusController::class)
        ->middleware('permission:memberships.manage')
        ->name('api.v1.tenant.memberships.status.update');
    Route::get('/customers', [CustomerController::class, 'index'])
        ->middleware('permission:customers.view_any')
        ->name('api.v1.customers.index');
    Route::post('/customers', [CustomerController::class, 'store'])
        ->middleware('permission:customers.manage')
        ->name('api.v1.customers.store');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])
        ->middleware('permission:customers.view_any')
        ->name('api.v1.customers.show');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])
        ->middleware('permission:customers.manage')
        ->name('api.v1.customers.update');
    Route::get('/equipments', [EquipmentController::class, 'index'])
        ->middleware('permission:equipments.view_any')
        ->name('api.v1.equipments.index');
    Route::post('/equipments', [EquipmentController::class, 'store'])
        ->middleware('permission:equipments.manage')
        ->name('api.v1.equipments.store');
    Route::get('/equipments/{equipment}', [EquipmentController::class, 'show'])
        ->middleware('permission:equipments.view_any')
        ->name('api.v1.equipments.show');
    Route::put('/equipments/{equipment}', [EquipmentController::class, 'update'])
        ->middleware('permission:equipments.manage')
        ->name('api.v1.equipments.update');
    Route::get('/intake-checklist-templates', [IntakeChecklistTemplateController::class, 'index'])
        ->middleware('permission:service_orders.manage')
        ->name('api.v1.intake-checklist-templates.index');
    Route::post('/intake-checklist-templates', [IntakeChecklistTemplateController::class, 'store'])
        ->middleware('permission:service_orders.manage')
        ->name('api.v1.intake-checklist-templates.store');
    Route::post('/service-orders/drafts', [ServiceOrderController::class, 'storeDraft'])
        ->middleware('permission:service_orders.manage')
        ->name('api.v1.service-orders.drafts.store');
    Route::put('/service-orders/drafts/{serviceOrder}', [ServiceOrderController::class, 'updateDraft'])
        ->middleware('permission:service_orders.manage')
        ->name('api.v1.service-orders.drafts.update');
    Route::post('/service-orders/drafts/{serviceOrder}/open', [ServiceOrderController::class, 'openDraft'])
        ->middleware('permission:service_orders.manage')
        ->name('api.v1.service-orders.drafts.open');
    Route::put('/service-orders/{serviceOrder}/diagnosis', [ServiceOrderController::class, 'updateDiagnosis'])
        ->middleware('permission:service_orders.diagnose')
        ->name('api.v1.service-orders.diagnosis.update');
    Route::get('/service-orders/{serviceOrder}/quotes', [QuoteRevisionController::class, 'index'])
        ->name('api.v1.service-orders.quotes.index');
    Route::post('/service-orders/{serviceOrder}/quotes', [QuoteRevisionController::class, 'store'])
        ->middleware('permission:service_orders.manage')
        ->name('api.v1.service-orders.quotes.store');
    Route::post('/service-orders/{serviceOrder}/quotes/{quoteRevision}/portal-token', QuotePortalTokenController::class)
        ->middleware('permission:service_orders.manage')
        ->name('api.v1.service-orders.quotes.portal-token.regenerate');
    Route::post('/service-orders/{serviceOrder}/execution/start', [ServiceOrderExecutionController::class, 'start'])
        ->middleware('permission:service_orders.execute')
        ->name('api.v1.service-orders.execution.start');
    Route::post('/service-orders/{serviceOrder}/execution/entries', [ServiceOrderExecutionController::class, 'recordWork'])
        ->middleware('permission:service_orders.execute')
        ->name('api.v1.service-orders.execution.entries.store');
    Route::post('/service-orders/{serviceOrder}/execution/complete', [ServiceOrderExecutionController::class, 'complete'])
        ->middleware('permission:service_orders.execute')
        ->name('api.v1.service-orders.execution.complete');
    Route::get('/service-orders/{serviceOrder}/financials', [ServiceOrderFinancialController::class, 'show'])
        ->middleware('permission:service_orders.finance')
        ->name('api.v1.service-orders.financials.show');
    Route::post('/service-orders/{serviceOrder}/financials/charges/derive-approved-quote', [ServiceOrderFinancialController::class, 'deriveApprovedQuoteCharge'])
        ->middleware('permission:service_orders.finance')
        ->name('api.v1.service-orders.financials.charges.derive-approved-quote');
    Route::post('/service-orders/{serviceOrder}/financials/charges/diagnostic-fee', [ServiceOrderFinancialController::class, 'addDiagnosticFee'])
        ->middleware('permission:service_orders.finance')
        ->name('api.v1.service-orders.financials.charges.diagnostic-fee');
    Route::post('/service-orders/{serviceOrder}/financials/charges/adjustments', [ServiceOrderFinancialController::class, 'addAdjustment'])
        ->middleware('permission:service_orders.finance')
        ->name('api.v1.service-orders.financials.charges.adjustments');
    Route::post('/service-orders/{serviceOrder}/financials/payments', [ServiceOrderFinancialController::class, 'recordPayment'])
        ->middleware('permission:service_orders.finance')
        ->name('api.v1.service-orders.financials.payments.store');
    Route::post('/service-orders/{serviceOrder}/ready-for-pickup', [ServiceOrderClosureController::class, 'readyForPickup'])
        ->middleware('permission:service_orders.manage')
        ->name('api.v1.service-orders.ready-for-pickup');
    Route::post('/service-orders/{serviceOrder}/delivery', [ServiceOrderClosureController::class, 'deliver'])
        ->middleware('permission:service_orders.manage')
        ->name('api.v1.service-orders.delivery');
    Route::patch('/service-orders/{serviceOrder}/assignment', ServiceOrderAssignmentController::class)
        ->middleware('permission:service_orders.manage')
        ->name('api.v1.service-orders.assignment.update');
    Route::post('/service-orders/{serviceOrder}/close/cancel', [ServiceOrderClosureController::class, 'cancel'])
        ->middleware('permission:service_orders.manage')
        ->name('api.v1.service-orders.close.cancel');
    Route::post('/service-orders/{serviceOrder}/close/no-repair', [ServiceOrderClosureController::class, 'noRepair'])
        ->middleware('permission:service_orders.manage')
        ->name('api.v1.service-orders.close.no-repair');
    Route::post('/service-orders/{serviceOrder}/warranty-returns', [ServiceOrderWarrantyController::class, 'store'])
        ->middleware('permission:service_orders.manage')
        ->name('api.v1.service-orders.warranty-returns.store');
    Route::patch('/service-orders/{serviceOrder}/warranty-coverage', [ServiceOrderWarrantyController::class, 'decide'])
        ->middleware('permission:service_orders.manage')
        ->name('api.v1.service-orders.warranty-coverage.update');
    Route::get('/service-orders/{serviceOrder}/timeline', [ServiceOrderController::class, 'timeline'])
        ->name('api.v1.service-orders.timeline');
    Route::get('/service-orders/{serviceOrder}/printable', [ServiceOrderController::class, 'printable'])
        ->name('api.v1.service-orders.printable');
    Route::get('/service-orders/reports/overview', [ServiceOrderController::class, 'overview'])
        ->name('api.v1.service-orders.reports.overview');
    Route::get('/service-orders/reports/service-orders', [ServiceOrderController::class, 'serviceOrderReport'])
        ->name('api.v1.service-orders.reports.service-orders');
    Route::get('/service-orders/reports/financial', [ServiceOrderController::class, 'financialReport'])
        ->name('api.v1.service-orders.reports.financial');
    Route::get('/service-orders', [ServiceOrderController::class, 'index'])->name('api.v1.service-orders.index');
    Route::get('/service-orders/{serviceOrder}', [ServiceOrderController::class, 'show'])
        ->name('api.v1.service-orders.show');
    Route::get('/service-orders/{serviceOrder}/attachments', [ServiceOrderAttachmentController::class, 'index'])
        ->name('api.v1.service-orders.attachments.index');
    Route::post('/service-orders/{serviceOrder}/attachments', [ServiceOrderAttachmentController::class, 'store'])
        ->name('api.v1.service-orders.attachments.store');
    Route::get('/service-orders/{serviceOrder}/attachments/{attachment}/download', [ServiceOrderAttachmentController::class, 'download'])
        ->name('api.v1.service-orders.attachments.download');
});
