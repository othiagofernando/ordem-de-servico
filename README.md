# Ordem de Servico

Foundation monorepo for the API-first, multi-tenant service-order platform.

## Structure

- `backend/`: Laravel 13 API, versioned under `/api/v1`
- `frontend/`: Vue 3 shell with TypeScript, Pinia, Vue Router, and Tailwind CSS

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

Foundation endpoints:

- `GET /api/v1/health`
- `GET /docs`

## Frontend workflow

```bash
cd frontend
npm install
npm run dev
```

The Vite dev server proxies `/api` and `/docs` to `http://127.0.0.1:8000` by default. Set `VITE_API_BASE_URL` if the backend is served elsewhere.

## Domain modules

The backend exposes these initial domain roots for future vertical slices:

- `Auth`
- `Companies`
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
