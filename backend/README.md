# Backend

Laravel 13 backend for the finished Ordem de Servico MVP.

## Available surface

- Versioned API prefix at `/api/v1`
- Health endpoint at `GET /api/v1/health`
- Tenant context endpoint at `GET /api/v1/tenant/context`
- Internal auth endpoints under `/api/v1/auth/*`
- Service-order, financial, portal, warranty, attachment, audit, and settings endpoints under `/api/v1/*`
- Generated Scribe docs at `GET /docs`
- Queue and cache baseline using Laravel's built-in configuration

## Useful commands

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan company:bootstrap "Acme Assistencia" "Admin User" admin@example.com --password=secret
php artisan serve
php artisan test
php artisan scribe:generate
php artisan queue:work
```

## MVP verification

Critical release commands:

```bash
php artisan scribe:generate
php artisan test
```

Critical end-to-end coverage lives in these feature tests:

- `tests/Feature/AuthenticationTest.php`
- `tests/Feature/MembershipInvitationWorkflowTest.php`
- `tests/Feature/ServiceOrderDraftIntakeTest.php`
- `tests/Feature/CustomerQuotePortalTest.php`
- `tests/Feature/ServiceOrderExecutionWorkflowTest.php`
- `tests/Feature/ServiceOrderFinancialWorkflowTest.php`
- `tests/Feature/ServiceOrderClosureWorkflowTest.php`
- `tests/Feature/ServiceOrderWarrantyWorkflowTest.php`
- `tests/Feature/ServiceOrderNotificationWorkflowTest.php`
- `tests/Feature/BusinessAuditTrailTest.php`
- `tests/Feature/StructuredLoggingTest.php`

See the repository release path in [../docs/release-checklist.md](/home/thiago/code/ordem-de-servico/repo/docs/release-checklist.md).
