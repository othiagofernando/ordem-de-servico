<?php

use App\Http\Controllers\Api\V1\Auth\ChangePasswordController;
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
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\BootstrapController;
use App\Http\Controllers\Api\V1\Auth\RecoverAccessController;
use App\Http\Controllers\Api\V1\Auth\SelectCompanyController;
use App\Http\Controllers\Api\V1\Admin\AdminAuditController;
use App\Http\Controllers\Api\V1\Admin\AdminAlertController;
use App\Http\Controllers\Api\V1\Admin\AdminBillingInvoiceController;
use App\Http\Controllers\Api\V1\Admin\AdminCompanyController;
use App\Http\Controllers\Api\V1\Admin\AdminCompanyAuditController;
use App\Http\Controllers\Api\V1\Admin\AdminCompanySubscriptionController;
use App\Http\Controllers\Api\V1\Admin\AdminImpersonationController;
use App\Http\Controllers\Api\V1\Admin\AdminPlanController;
use App\Http\Controllers\Api\V1\Admin\AdminOverviewController;
use App\Http\Controllers\Api\V1\Admin\AdminSubscriptionController;
use App\Http\Controllers\Api\V1\Admin\AdminAdminRoleController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\BusinessAuditController;
use App\Http\Controllers\Api\V1\CompanyOperationalSettingController;
use App\Http\Controllers\Api\V1\CompanyLogoController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\CustomerQuotePortalController;
use App\Http\Controllers\Api\V1\BillingCheckoutController;
use App\Http\Controllers\Api\V1\BillingSubscriptionCancelController;
use App\Http\Controllers\Api\V1\BillingWebhookController;
use App\Http\Controllers\Api\V1\EquipmentController;
use App\Http\Controllers\Api\V1\EquipmentEvidenceController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\IntakeChecklistTemplateController;
use App\Http\Controllers\Api\V1\MembershipAuthorizationController;
use App\Http\Controllers\Api\V1\MembershipStatusController;
use App\Http\Controllers\Api\V1\QuotePortalTokenController;
use App\Http\Controllers\Api\V1\QuoteRevisionController;
use App\Http\Controllers\Api\V1\PublicTenantLookupController;
use App\Http\Controllers\Api\V1\ServiceOrderAttachmentController;
use App\Http\Controllers\Api\V1\ServiceOrderDeleteController;
use App\Http\Controllers\Api\V1\ServiceOrderForceDeleteController;
use App\Http\Controllers\Api\V1\ServiceOrderRestoreController;
use App\Http\Controllers\Api\V1\ServiceOrderTrashController;
use App\Http\Controllers\Api\V1\ServiceOrderClosureController;
use App\Http\Controllers\Api\V1\ServiceOrderController;
use App\Http\Controllers\Api\V1\ServiceOrderDocumentPrintController;
use App\Http\Controllers\Api\V1\ServiceOrderExecutionController;
use App\Http\Controllers\Api\V1\ServiceOrderFinancialController;
use App\Http\Controllers\Api\V1\ServiceOrderWarrantyController;
use App\Http\Controllers\Api\V1\ServiceOrderAssignmentController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\TenantContextController;
use App\Http\Controllers\Api\V1\TenantMembershipController;
use App\Http\Controllers\Api\V1\TenantMembershipDeleteController;
use App\Http\Controllers\Api\V1\TenantMembershipInvitationController;
use App\Http\Controllers\Api\V1\TenantMembershipInvitationResendController;
use App\Http\Controllers\Api\V1\TenantMembershipListController;
use App\Http\Controllers\Api\V1\TenantMembershipUserPasswordController;
use App\Http\Controllers\Api\V1\TenantMembershipUserUpdateController;
use App\Http\Controllers\Api\V1\RoleCatalogController;
use App\Http\Controllers\Api\V1\CepLookupController;
use App\Http\Controllers\Api\V1\UserNotificationController;
use App\Http\Controllers\Api\V1\PartController;
use App\Http\Controllers\Api\V1\PlaybookController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\ServiceOrderPartController;
use App\Http\Controllers\Api\V1\Admin\AdminPlaybookController;
use App\Http\Controllers\Api\V1\Admin\AdminSearchController;
use App\Http\Controllers\Api\V1\PortalRatingController;
use App\Http\Controllers\Api\V1\RatingsController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('api.v1.health');
Route::post('/register', RegisterController::class)->name('api.v1.register');
Route::get('/public/tenants/{slug}', PublicTenantLookupController::class)->name('api.v1.public.tenants.show');
Route::get('/cep/{cep}', CepLookupController::class)->name('api.v1.cep.lookup');

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
    Route::post('/recover-access', RecoverAccessController::class)
        ->name('api.v1.auth.recover-access');
});

Route::prefix('portal')->group(function (): void {
    Route::get('/quotes/{token}', [CustomerQuotePortalController::class, 'show'])
        ->name('api.v1.portal.quotes.show');
    Route::post('/quotes/{token}/decision', [CustomerQuotePortalController::class, 'decide'])
        ->name('api.v1.portal.quotes.decision');
    Route::get('/quotes/{token}/documents/quote', [CustomerQuotePortalController::class, 'documentQuote'])
        ->name('api.v1.portal.quotes.documents.quote');
    Route::get('/quotes/{token}/documents/payments/{payment}/receipt', [CustomerQuotePortalController::class, 'documentReceipt'])
        ->name('api.v1.portal.quotes.documents.receipt');
    Route::get('/quotes/{token}/documents/delivery-term', [CustomerQuotePortalController::class, 'documentDelivery'])
        ->name('api.v1.portal.quotes.documents.delivery');
    Route::get('/quotes/{token}/history', [CustomerQuotePortalController::class, 'history'])
        ->name('api.v1.portal.quotes.history');
    Route::post('/quotes/{token}/rating', [PortalRatingController::class, 'store'])
        ->name('api.v1.portal.quotes.rating');
});

Route::post('/billing/webhooks/asaas', [BillingWebhookController::class, 'asaas'])
    ->name('api.v1.billing.webhooks.asaas');

Route::middleware('auth.token')->group(function (): void {
    Route::post('/auth/logout', LogoutController::class)->name('api.v1.auth.logout');
    Route::put('/auth/me/password', ChangePasswordController::class)->name('api.v1.auth.me.password');
    Route::get('/auth/bootstrap', BootstrapController::class)->name('api.v1.auth.bootstrap');
    Route::get('/auth/permissions/me', PermissionProfileController::class)->name('api.v1.auth.permissions.me');
    Route::get('/subscription', SubscriptionController::class)->name('api.v1.subscription.show');
    Route::post('/subscription/checkout', BillingCheckoutController::class)->name('api.v1.subscription.checkout');
    Route::post('/subscription/cancel', BillingSubscriptionCancelController::class)->name('api.v1.subscription.cancel');
    Route::get('/audit-logs', BusinessAuditController::class)
        ->middleware('permission:company_settings.manage')
        ->name('api.v1.audit-logs.index');
    Route::get('/tenant/context', TenantContextController::class)->name('api.v1.tenant.context');
    Route::prefix('admin')->middleware('super_admin')->group(function (): void {
        Route::get('/overview', AdminOverviewController::class)->name('api.v1.admin.overview');
        Route::get('/alerts', AdminAlertController::class)->name('api.v1.admin.alerts');
        Route::get('/audits', [AdminAuditController::class, 'index'])->name('api.v1.admin.audits.index');
        Route::get('/audits/{audit}', [AdminAuditController::class, 'show'])->name('api.v1.admin.audits.show');
        Route::get('/billing/invoices', [AdminBillingInvoiceController::class, 'index'])->name('api.v1.admin.billing.invoices.index');
        Route::get('/billing/invoices/{billingInvoice}', [AdminBillingInvoiceController::class, 'show'])->name('api.v1.admin.billing.invoices.show');
        Route::get('/plans', [AdminPlanController::class, 'index'])->name('api.v1.admin.plans.index');
        Route::post('/plans', [AdminPlanController::class, 'store'])->name('api.v1.admin.plans.store');
        Route::get('/plans/{plan}', [AdminPlanController::class, 'show'])->name('api.v1.admin.plans.show');
        Route::put('/plans/{plan}', [AdminPlanController::class, 'update'])->name('api.v1.admin.plans.update');
        Route::delete('/plans/{plan}', [AdminPlanController::class, 'destroy'])->name('api.v1.admin.plans.destroy');
        Route::get('/subscriptions', [AdminSubscriptionController::class, 'index'])->name('api.v1.admin.subscriptions.index');
        Route::get('/companies', [AdminCompanyController::class, 'index'])->name('api.v1.admin.companies.index');
        Route::post('/companies', [AdminCompanyController::class, 'store'])->name('api.v1.admin.companies.store');
        Route::get('/companies/{company}', [AdminCompanyController::class, 'show'])->name('api.v1.admin.companies.show');
        Route::put('/companies/{company}', [AdminCompanyController::class, 'update'])->name('api.v1.admin.companies.update');
        Route::get('/companies/{company}/audits', [AdminCompanyAuditController::class, 'index'])->name('api.v1.admin.companies.audits.index');
        Route::put('/companies/{company}/subscription', [AdminCompanySubscriptionController::class, 'update'])->name('api.v1.admin.companies.subscription.update');
        Route::post('/companies/{company}/subscription/extend', [AdminCompanySubscriptionController::class, 'extend'])->name('api.v1.admin.companies.subscription.extend');
        Route::patch('/companies/{company}/suspend', [AdminCompanyController::class, 'suspend'])->name('api.v1.admin.companies.suspend');
        Route::patch('/companies/{company}/activate', [AdminCompanyController::class, 'activate'])->name('api.v1.admin.companies.activate');
        Route::delete('/companies/{company}', [AdminCompanyController::class, 'destroy'])->name('api.v1.admin.companies.destroy');
        Route::get('/companies/{company}/memberships', [AdminImpersonationController::class, 'memberships'])->name('api.v1.admin.companies.memberships.index');
        Route::post('/companies/{company}/memberships/{membership}/impersonate', [AdminImpersonationController::class, 'impersonate'])->name('api.v1.admin.companies.memberships.impersonate');
        // Busca unificada admin
        Route::get('/search', AdminSearchController::class)->name('api.v1.admin.search');
        // Playbooks — gerenciados pelo admin da plataforma
        Route::get('/playbooks', [AdminPlaybookController::class, 'index'])->name('api.v1.admin.playbooks.index');
        Route::post('/playbooks', [AdminPlaybookController::class, 'store'])->name('api.v1.admin.playbooks.store');
        Route::get('/playbooks/{playbook}', [AdminPlaybookController::class, 'show'])->name('api.v1.admin.playbooks.show');
        Route::put('/playbooks/{playbook}', [AdminPlaybookController::class, 'update'])->name('api.v1.admin.playbooks.update');
        Route::delete('/playbooks/{playbook}', [AdminPlaybookController::class, 'destroy'])->name('api.v1.admin.playbooks.destroy');

        Route::middleware('super_admin:super_admin')->group(function (): void {
            Route::get('/users', [AdminUserController::class, 'index'])->name('api.v1.admin.users.index');
            Route::post('/users', [AdminUserController::class, 'store'])->name('api.v1.admin.users.store');
            Route::patch('/users/{user}', [AdminUserController::class, 'update'])->name('api.v1.admin.users.update');
            Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('api.v1.admin.users.destroy');

            Route::get('/roles', [AdminAdminRoleController::class, 'index'])->name('api.v1.admin.roles.index');
            Route::post('/roles', [AdminAdminRoleController::class, 'store'])->name('api.v1.admin.roles.store');
            Route::put('/roles/{adminRole}', [AdminAdminRoleController::class, 'update'])->name('api.v1.admin.roles.update');
            Route::delete('/roles/{adminRole}', [AdminAdminRoleController::class, 'destroy'])->name('api.v1.admin.roles.destroy');
        });
    });
    // Notifications
    Route::get('/notifications', [UserNotificationController::class, 'index'])->name('api.v1.notifications.index');
    Route::post('/notifications/{notification}/read', [UserNotificationController::class, 'markRead'])->name('api.v1.notifications.read');
    Route::post('/notifications/read-all', [UserNotificationController::class, 'markAllRead'])->name('api.v1.notifications.read-all');
    // Broadcasting channel auth — uses our Bearer-token middleware instead of session auth
    Route::post('/broadcasting/auth', function (\Illuminate\Http\Request $request) {
        return \Illuminate\Support\Facades\Broadcast::auth($request);
    })->name('api.v1.broadcasting.auth');

    Route::middleware('subscription.active')->group(function (): void {
    Route::get('/company/settings', [CompanyOperationalSettingController::class, 'show'])
        ->middleware('permission:company_settings.manage')
        ->name('api.v1.company.settings.show');
    Route::put('/company/settings', [CompanyOperationalSettingController::class, 'update'])
        ->middleware('permission:company_settings.manage')
        ->name('api.v1.company.settings.update');
    Route::get('/settings', [CompanyOperationalSettingController::class, 'show'])
        ->middleware('permission:company_settings.manage')
        ->name('api.v1.settings.show');
    Route::put('/settings', [CompanyOperationalSettingController::class, 'update'])
        ->middleware('permission:company_settings.manage')
        ->name('api.v1.settings.update');
    Route::post('/settings/logo', CompanyLogoController::class)
        ->middleware('permission:company_settings.manage')
        ->name('api.v1.settings.logo');
    Route::get('/tenant/roles', RoleCatalogController::class)
        ->name('api.v1.tenant.roles.index');
    Route::get('/tenant/memberships', TenantMembershipListController::class)
        ->name('api.v1.tenant.memberships.index');
    Route::get('/tenant/memberships/{membership}', TenantMembershipController::class)
        ->name('api.v1.tenant.memberships.show');
    Route::post('/tenant/memberships/invitations', TenantMembershipInvitationController::class)
        ->middleware('permission:memberships.manage')
        ->name('api.v1.tenant.memberships.invitations.store');
    Route::post('/tenant/memberships/{membership}/invitations/resend', TenantMembershipInvitationResendController::class)
        ->middleware('permission:memberships.manage')
        ->name('api.v1.tenant.memberships.invitations.resend');
    Route::patch('/tenant/memberships/{membership}/authorization', MembershipAuthorizationController::class)
        ->middleware('permission:memberships.manage')
        ->name('api.v1.tenant.memberships.authorization.update');
    Route::patch('/tenant/memberships/{membership}/status', MembershipStatusController::class)
        ->middleware('permission:memberships.manage')
        ->name('api.v1.tenant.memberships.status.update');
    Route::delete('/tenant/memberships/{membership}', TenantMembershipDeleteController::class)
        ->middleware('permission:memberships.manage')
        ->name('api.v1.tenant.memberships.destroy');
    Route::patch('/tenant/memberships/{membership}/user', TenantMembershipUserUpdateController::class)
        ->middleware('permission:memberships.manage')
        ->name('api.v1.tenant.memberships.user.update');
    Route::put('/tenant/memberships/{membership}/user/password', TenantMembershipUserPasswordController::class)
        ->middleware('permission:memberships.manage')
        ->name('api.v1.tenant.memberships.user.password');
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
    Route::get('/equipments/{equipment}/evidences', [EquipmentEvidenceController::class, 'index'])
        ->middleware('permission:equipments.view_any')
        ->name('api.v1.equipment.evidences.index');
    Route::post('/equipments/{equipment}/evidences', [EquipmentEvidenceController::class, 'store'])
        ->middleware('permission:equipments.manage')
        ->name('api.v1.equipment.evidences.store');
    Route::get('/equipments/{equipment}/evidences/{evidence}/download', [EquipmentEvidenceController::class, 'download'])
        ->middleware('permission:equipments.view_any')
        ->name('api.v1.equipment.evidences.download');
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
    Route::delete('/service-orders/{serviceOrder}', ServiceOrderDeleteController::class)
        ->middleware('permission:service_orders.manage')
        ->name('api.v1.service-orders.destroy');
    Route::get('/service-orders/trash', ServiceOrderTrashController::class)
        ->middleware('permission:service_orders.manage')
        ->name('api.v1.service-orders.trash.index');
    Route::post('/service-orders/{serviceOrderId}/restore', ServiceOrderRestoreController::class)
        ->middleware('permission:service_orders.manage')
        ->name('api.v1.service-orders.restore');
    Route::delete('/service-orders/{serviceOrderId}/force', ServiceOrderForceDeleteController::class)
        ->middleware('permission:service_orders.manage')
        ->name('api.v1.service-orders.force-delete');
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
    Route::get('/service-orders/{serviceOrder}/documents/quote', [ServiceOrderDocumentPrintController::class, 'quote'])
        ->name('api.v1.service-orders.documents.quote');
    Route::get('/service-orders/{serviceOrder}/documents/payments/{payment}/receipt', [ServiceOrderDocumentPrintController::class, 'receipt'])
        ->name('api.v1.service-orders.documents.receipt');
    Route::get('/service-orders/{serviceOrder}/documents/delivery-term', [ServiceOrderDocumentPrintController::class, 'delivery'])
        ->name('api.v1.service-orders.documents.delivery');
    Route::get('/service-orders/reports/overview', [ServiceOrderController::class, 'overview'])
        ->name('api.v1.service-orders.reports.overview');
    Route::get('/service-orders/reports/service-orders', [ServiceOrderController::class, 'serviceOrderReport'])
        ->name('api.v1.service-orders.reports.service-orders');
    Route::get('/service-orders/reports/financial', [ServiceOrderController::class, 'financialReport'])
        ->name('api.v1.service-orders.reports.financial');
    Route::get('/service-orders/reports/trends', [ServiceOrderController::class, 'trendsReport'])
        ->name('api.v1.service-orders.reports.trends');
    Route::get('/service-orders/reports/by-technician', [ServiceOrderController::class, 'technicianReport'])
        ->name('api.v1.service-orders.reports.by-technician');
    Route::get('/service-orders/{serviceOrder}/attachments', [ServiceOrderAttachmentController::class, 'index'])
        ->name('api.v1.service-orders.attachments.index');
    Route::post('/service-orders/{serviceOrder}/attachments', [ServiceOrderAttachmentController::class, 'store'])
        ->name('api.v1.service-orders.attachments.store');
    Route::get('/service-orders/{serviceOrder}/attachments/{attachment}/download', [ServiceOrderAttachmentController::class, 'download'])
        ->name('api.v1.service-orders.attachments.download');
    Route::get('/service-orders', [ServiceOrderController::class, 'index'])->name('api.v1.service-orders.index');
    Route::get('/service-orders/{serviceOrder}', [ServiceOrderController::class, 'show'])
        ->name('api.v1.service-orders.show');
    // Busca unificada tenant
    Route::get('/search', SearchController::class)->name('api.v1.search');
    // Playbooks — consulta (somente leitura) para usuários da empresa
    Route::get('/playbooks', [PlaybookController::class, 'index'])
        ->name('api.v1.playbooks.index');
    Route::get('/playbooks/{playbook}', [PlaybookController::class, 'show'])
        ->name('api.v1.playbooks.show');
    // Peças
    Route::get('/parts', [PartController::class, 'index'])->middleware('permission:parts.view_any')->name('api.v1.parts.index');
    Route::post('/parts', [PartController::class, 'store'])->middleware('permission:parts.manage')->name('api.v1.parts.store');
    Route::get('/parts/{part}', [PartController::class, 'show'])->middleware('permission:parts.view_any')->name('api.v1.parts.show');
    Route::put('/parts/{part}', [PartController::class, 'update'])->middleware('permission:parts.manage')->name('api.v1.parts.update');
    Route::delete('/parts/{part}', [PartController::class, 'destroy'])->middleware('permission:parts.manage')->name('api.v1.parts.destroy');
    Route::patch('/parts/{part}/stock', [PartController::class, 'adjustStock'])->middleware('permission:parts.manage')->name('api.v1.parts.stock.adjust');
    Route::post('/service-orders/{serviceOrder}/parts', [ServiceOrderPartController::class, 'store'])->middleware('permission:parts.manage')->name('api.v1.service-orders.parts.store');
    Route::delete('/service-orders/{serviceOrder}/parts/{serviceOrderPart}', [ServiceOrderPartController::class, 'destroy'])->middleware('permission:parts.manage')->name('api.v1.service-orders.parts.destroy');
    // Avaliações do cliente
    Route::get('/ratings', [RatingsController::class, 'index'])->middleware('permission:service_orders.view_any')->name('api.v1.ratings.index');
    });
});
