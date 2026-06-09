# Frontend

Vue 3 + TypeScript frontend for the Ordem de Servico MVP.

## Install and run

```bash
npm install
npm run dev
```

The Vite dev server expects the Laravel API at `http://127.0.0.1:8000` and proxies `/api` and `/docs` there by default.

If the backend runs elsewhere, set:

```bash
VITE_API_BASE_URL=http://your-backend-host/api/v1
```

## Verification

```bash
npm run build
```

This is the required frontend release check for the MVP.
