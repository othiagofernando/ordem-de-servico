# Backend

Laravel 13 backend for the service-order platform foundation and tenant bootstrap slices.

## Available surface

- Versioned API prefix at `/api/v1`
- Health endpoint at `GET /api/v1/health`
- Tenant context endpoint at `GET /api/v1/tenant/context`
- Internal auth endpoints under `/api/v1/auth/*`
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
