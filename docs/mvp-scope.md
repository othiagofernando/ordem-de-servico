# MVP Scope

This document turns [CONTEXT.md](/home/thiago/code/ordem-de-servico/repo/CONTEXT.md) into an execution scope for the next consolidation phase.

## Goal

The product should be considered MVP-complete when one operator can move a Service Order from intake to closure with a coherent workflow and without relying on side channels.

The purpose of this scope is to stop treating all existing features as equally important.

## In Scope Now

These areas stay on the main implementation path:

- login and active Company selection
- Customer creation and lookup
- Equipment creation and lookup
- draft intake
- formal Service Order opening
- diagnosis registration
- Quote Revision creation
- customer quote decision
- execution start
- work entry registration
- technical completion
- charge derivation or manual charge registration
- payment registration
- delivery
- terminal closure outcomes
- basic operational dashboard and service-order list

## Supported But Secondary

These areas may remain implemented, but should not drive architecture or release readiness:

- checklist template versioning
- service order numbering customization
- notification message customization
- operator assignment customization
- delivery balance override policy
- document printing surfaces

## Deferred Or Hidden From Main Flow

These areas should be treated as post-MVP or hidden from the main operating path until the core flow is simpler:

- passwordless code login
- magic link login
- warranty return flow
- advanced customer portal experience beyond quote decision
- advanced reporting and CSV-first workflows
- extensive branding controls
- secondary health/demo views

## Recommended Product Decisions

These recommendations are based on reducing workflow friction while keeping operational safety:

1. Execution should require diagnosis, but not necessarily a customer-approved quote in every business scenario.
2. Technician assignment should be recommended before execution, but not a hard gate unless the operation explicitly depends on it.
3. Delivery with outstanding balance should remain configurable and auditable, not universally mandatory.
4. Warranty should be removed from the main MVP path and treated as a later expansion.
5. The customer portal should remain optional infrastructure for quote decision, not the only supported commercial channel.

## Release Gate For The Consolidation Phase

The next release should be considered healthy only if all of the following are true:

- backend tests pass
- frontend build passes
- the intake-to-delivery flow is still coherent
- frontend and backend contracts match for the visible MVP surfaces
- secondary features do not add ambiguity to the main operator flow

## Next Refactor Target

The next implementation pass should split the Service Order flow into three seams:

- Intake
- Commercial
- Operational

That refactor should begin in the backend orchestration layer first, then the frontend detail screen.
