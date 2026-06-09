# Introduction

Executable API documentation for the Ordem de Servico MVP.

<aside>
    <strong>Base URL</strong>: <code>http://ordem-de-servico.test</code>
</aside>

    This documentation describes the current MVP contract for the multi-tenant Ordem de Servico platform.

    The repository keeps the release surface aligned through executable feature tests and generated docs:

    - authentication and active-company context
    - intake draft to formal service order
    - quote revision and customer portal decision
    - execution, financial closure, and delivery
    - warranty return, notifications, audit trail, and structured logging

    Release verification is documented in `docs/release-checklist.md`, and the critical workflow tests under `tests/Feature/*` are the source of truth for end-to-end behavior.

