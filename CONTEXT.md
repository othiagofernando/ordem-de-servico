# Ordem de Servico Context

## Objective

This product exists to let a service company open, advance, charge, and close a service order with enough operational traceability to run the day-to-day workflow without relying on paper, chat history, or informal handoff.

The main success criterion is not feature count. The main success criterion is that one operator can take a Service Order from intake to delivery using one coherent workflow.

## Core Concepts

- **Company**: the tenant that owns all operational data.
- **Membership**: the operator's role and permissions inside one Company.
- **Customer**: the person or business requesting service.
- **Equipment**: the item being received, diagnosed, repaired, and delivered.
- **Service Order**: the central operational record. All important workflow decisions should hang off this module.
- **Quote Revision**: the commercial proposal for a Service Order.
- **Execution Entry**: labor or part usage recorded during technical work.
- **Charge**: an amount the Company intends to collect for a Service Order.
- **Payment**: money actually received against a Service Order.
- **Timeline Event**: an immutable operational event attached to a Service Order.

## MVP Goal

The MVP is complete when the system can support this flow reliably:

1. Authenticate an operator and resolve the active Company.
2. Register or select a Customer.
3. Register or select Equipment.
4. Open a Service Order from intake.
5. Record diagnosis.
6. Register a Quote Revision.
7. Record the customer's decision on the quote.
8. Start and complete execution.
9. Record charges and payments.
10. Deliver or otherwise close the Service Order.

If a feature does not make this flow clearer, safer, or easier to operate, it is not part of the core MVP.

## Mandatory Now

These capabilities are part of the MVP core and should remain on the critical path:

- tenant-aware authentication with active Company selection
- Customer registry
- Equipment registry
- draft intake and formal Service Order opening
- diagnosis capture
- quote creation and quote decision
- execution start, work logging, and technical completion
- financial summary with charge and payment registration
- delivery and terminal closure states
- basic operator-facing queue visibility for active Service Orders

## Configurable But Not Core

These capabilities may stay in the product, but they must not dominate the architecture or block MVP completion:

- delivery blocking when outstanding balance exists
- notification message text customization
- service order prefix customization
- checklist template versioning
- assignment policies for technicians
- whether execution requires approved quote before starting
- whether execution requires assignment before starting

These rules need explicit product decisions. They should be documented as operational policy, not left implicit in controller code.

## Post-MVP Or Hidden Until Stable

These capabilities should be treated as post-MVP, optional, or hidden from the main flow until the core workflow is consolidated:

- passwordless login and magic link login
- warranty return workflow
- advanced printable document surfaces
- customer portal refinements beyond quote decision
- extensive branding and communication customization
- advanced CSV/reporting surfaces
- detailed override auditing beyond what is needed for safe operation
- secondary or orphan frontend views not used in the core workflow

## Product Rules To Confirm

These rules appear in the current implementation and must be confirmed as product decisions before further refactoring:

- A Service Order may only start execution after an approved Quote Revision.
- A Service Order may require an assigned technician before execution starts.
- Delivery may be blocked when there is outstanding balance, unless an authorized override is used.
- Warranty return may only start from a delivered Service Order.
- The customer portal may be part of the main commercial flow or may be an optional channel.

If any of these rules are not truly load-bearing in the business, they should be downgraded from mandatory workflow gates to optional policy.

## Architecture Direction

The next architectural pass should deepen the Service Order module around three seams:

- **Intake module**: intake draft, opening checks, intake snapshots, intake checklist
- **Commercial module**: diagnosis, quote lifecycle, customer decision
- **Operational module**: execution, financials, delivery, terminal closure

The main goal is to reduce logic spread across large controllers and large frontend views, especially around Service Order orchestration.

## Current Risks

- business rules are spread across large controllers instead of concentrated behind smaller interfaces
- frontend and backend contract drift already exists in at least some payment/reporting values
- global tenant and membership helpers reduce clarity around authorization and Company context
- the repository lacks ADRs, so business rules are encoded in tests and controllers instead of explicit decisions
- optional features have comparable visual and architectural weight to core workflow features

## Immediate Priorities

1. Confirm which product rules are truly mandatory.
2. Reduce the main Service Order backend surface into smaller workflow modules.
3. Split the main Service Order frontend detail screen by workflow step.
4. Fix frontend/backend contract mismatches before adding more features.
5. Hide or defer post-MVP surfaces until the core workflow is clean and demonstrable.
