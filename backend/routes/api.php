<?php

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\PasswordlessCodeRequestController;
use App\Http\Controllers\Api\V1\Auth\PasswordlessCodeVerifyController;
use App\Http\Controllers\Api\V1\Auth\PasswordlessMagicLinkConsumeController;
use App\Http\Controllers\Api\V1\Auth\PasswordlessMagicLinkRequestController;
use App\Http\Controllers\Api\V1\Auth\PermissionProfileController;
use App\Http\Controllers\Api\V1\Auth\SelectCompanyController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MembershipAuthorizationController;
use App\Http\Controllers\Api\V1\ServiceOrderController;
use App\Http\Controllers\Api\V1\TenantContextController;
use App\Http\Controllers\Api\V1\TenantMembershipController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('api.v1.health');

Route::prefix('auth')->group(function (): void {
    Route::post('/login', LoginController::class)->name('api.v1.auth.login');
    Route::post('/select-company', SelectCompanyController::class)->name('api.v1.auth.select-company');
    Route::post('/passwordless/code/request', PasswordlessCodeRequestController::class)
        ->name('api.v1.auth.passwordless.code.request');
    Route::post('/passwordless/code/verify', PasswordlessCodeVerifyController::class)
        ->name('api.v1.auth.passwordless.code.verify');
    Route::post('/passwordless/magic-link/request', PasswordlessMagicLinkRequestController::class)
        ->name('api.v1.auth.passwordless.magic-link.request');
    Route::post('/passwordless/magic-link/consume', PasswordlessMagicLinkConsumeController::class)
        ->name('api.v1.auth.passwordless.magic-link.consume');
});

Route::middleware('auth.token')->group(function (): void {
    Route::get('/auth/permissions/me', PermissionProfileController::class)->name('api.v1.auth.permissions.me');
    Route::get('/tenant/context', TenantContextController::class)->name('api.v1.tenant.context');
    Route::get('/tenant/memberships/{membership}', TenantMembershipController::class)
        ->name('api.v1.tenant.memberships.show');
    Route::patch('/tenant/memberships/{membership}/authorization', MembershipAuthorizationController::class)
        ->middleware('permission:memberships.manage')
        ->name('api.v1.tenant.memberships.authorization.update');
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
    Route::get('/service-orders', [ServiceOrderController::class, 'index'])->name('api.v1.service-orders.index');
    Route::get('/service-orders/{serviceOrder}', [ServiceOrderController::class, 'show'])
        ->name('api.v1.service-orders.show');
});
