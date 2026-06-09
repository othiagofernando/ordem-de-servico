# Ordem de Servico

API-first, multi-tenant service-order MVP with Laravel 13 backend and Vue 3 frontend.

## Structure

- `backend/`: Laravel 13 API, versioned under `/api/v1`
- `frontend/`: Vue 3 shell with TypeScript, Pinia, Vue Router, and Tailwind CSS
- `docs/`: release checklist and operator-facing repository documentation

## MVP scope

The current repository covers the MVP flow from issue `#1`:

- global auth and active-company membership context
- customer and equipment registry
- intake drafts and formal service-order opening
- diagnosis, quote revisions, and customer portal approval
- execution, work tracking, technical completion, and delivery
- financial derivation, partial payments, receipts, and critical notifications
- warranty returns, business audit, and structured logging

## Backend workflow

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Useful checks:

```bash
php artisan test
php artisan queue:work
php artisan scribe:generate
php artisan route:list
```

Core endpoints:

- `GET /api/v1/health`
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/select-company`
- `GET /api/v1/tenant/context`
- `GET /api/v1/service-orders`
- `GET /api/v1/audit-logs`
- `GET /docs`

Tenant bootstrap:

```bash
cd backend
php artisan company:bootstrap "Acme Assistencia" "Admin User" admin@example.com --password=secret
```

The command creates the first company administrator, company membership, and a company-bound API token for authenticated tenant requests.

## Frontend workflow

```bash
cd frontend
npm install
npm run dev
```

The Vite dev server proxies `/api` and `/docs` to `http://127.0.0.1:8000` by default. Set `VITE_API_BASE_URL` if the backend is served elsewhere.

## Release verification

The MVP release path is documented in [docs/release-checklist.md](/home/thiago/code/ordem-de-servico/repo/docs/release-checklist.md).

Minimum release commands:

```bash
cd backend
php artisan scribe:generate
php artisan test

cd ../frontend
npm run build
```

## Domain modules

The backend currently exposes these domain roots:

- `Auth`
- `Companies`
- `Auditing`
- `Users`
- `Customers`
- `Equipments`
- `ServiceOrders`
- `Quotes`
- `Finance`
- `Warranty`
- `Notifications`
- `Reports`
- `Settings`
