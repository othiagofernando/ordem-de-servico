# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer {YOUR_AUTH_KEY}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

Authenticate through `/api/v1/auth/login`, then select the active company through `/api/v1/auth/select-company` to obtain a company-bound bearer token.
