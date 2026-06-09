# MVP Release Checklist

This checklist is the release verification path for the finished MVP described in issue `#1`.

## 1. Environment bootstrap

Backend:

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Frontend:

```bash
cd frontend
npm install
```

## 2. Generate executable API docs

```bash
cd backend
php artisan scribe:generate
```

Expected result:

- `GET /docs` renders the current API surface
- Postman collection and OpenAPI spec are refreshed in `storage/app/scribe/`

## 3. Backend verification

Run the full backend suite:

```bash
cd backend
php artisan test
```

Critical MVP flows covered by executable tests:

- Authentication and active-company selection:
  `tests/Feature/AuthenticationTest.php`
- Membership invitation lifecycle:
  `tests/Feature/MembershipInvitationWorkflowTest.php`
- Intake draft to formal service order:
  `tests/Feature/ServiceOrderDraftIntakeTest.php`
- Intake evidence and equipment snapshot:
  `tests/Feature/ServiceOrderIntakeEvidenceTest.php`
- Quote revisions and portal token lifecycle:
  `tests/Feature/QuoteRevisionWorkflowTest.php`
- Customer portal and quote decision:
  `tests/Feature/CustomerQuotePortalTest.php`
- Execution start, work entries, and technical completion:
  `tests/Feature/ServiceOrderExecutionWorkflowTest.php`
- Financial derivation, payments, and immutable receipts:
  `tests/Feature/ServiceOrderFinancialWorkflowTest.php`
- Delivery, terminal outcomes, and overrides:
  `tests/Feature/ServiceOrderClosureWorkflowTest.php`
- Warranty return flow:
  `tests/Feature/ServiceOrderWarrantyWorkflowTest.php`
- Critical notifications:
  `tests/Feature/ServiceOrderNotificationWorkflowTest.php`
- Sensitive business audit:
  `tests/Feature/BusinessAuditTrailTest.php`
- Structured logging context:
  `tests/Feature/StructuredLoggingTest.php`

## 4. Frontend verification

```bash
cd frontend
npm run build
```

Expected result:

- Vue application compiles without type errors
- Production assets are emitted to `frontend/dist/`

## 5. Operational smoke path

Before calling the MVP releasable, confirm these flows on the current branch:

1. Log in and select the active company.
2. Create a customer and equipment.
3. Start an intake draft and open the service order.
4. Record diagnosis and submit a quote.
5. Open the customer portal link and approve or reject the quote.
6. Start execution, register work, and complete technical work.
7. Derive charges, record payment, and verify receipt notification behavior.
8. Mark the order ready for pickup and deliver it.
9. Open a warranty return from a delivered order.
10. Confirm `/docs` and `/api/v1/audit-logs` behave as expected for an authorized operator.

## 6. Release outcome

The branch is considered MVP-ready only when all of the following are true:

- API docs generate successfully
- backend tests pass
- frontend build passes
- the smoke path above is still coherent with the documented product contract
