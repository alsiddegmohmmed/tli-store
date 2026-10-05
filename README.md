# Store POS and accounting platform

Arabic RTL web platform for four cashier stations, connected stock, purchases, expenses and employee advances. The first release is online-only and must issue official Saudi invoices. Opening target: 1 November 2026.

**Status:** documentation only; the stack is selected. No application, installed dependencies, setup commands or application tests exist yet. VAT registration is confirmed by the owner; company-specific Phase 2 integration details and physical device compatibility still need verification.

Selected stack: **Laravel + React/TypeScript through Inertia + PostgreSQL**, using Tailwind and shadcn/ui for shared Arabic RTL components. Versions, rationale and integration boundaries are maintained in the [Engineering guide](docs/engineering-guide.md#selected-stack).

## Document map

| Document | Responsibility |
| --- | --- |
| [AGENTS.md](AGENTS.md) | Shared working instructions for coding agents |
| [CLAUDE.md](CLAUDE.md) | Imports the shared instructions for Claude Code |
| [MVP workflows](docs/store-mvp-workflows.md) | Product scope, transaction effects, migration needs and acceptance outcomes |
| [Engineering guide](docs/engineering-guide.md) | Module boundaries, reuse, Arabic RTL UX, styling and growth strategy |
| [Reference review](docs/reference-system-review.md) | Evidence from Technolink; historical observations, not product authority |

Read the relevant section instead of loading every document for every task. Keep each decision in its owning document and link to it from elsewhere. Update setup and verification commands here only after an implementation exists and the commands have been checked.

## Working approach

Use one modular application and a relational database. Deliver small complete workflows. Share real business rules and reusable UI components; add infrastructure and abstractions only when a present need justifies them.

Write the product in Arabic directly. Prioritize clear workflows and reliable transactions. A small theme-token layer and shared components will make later visual changes easier without delaying the functional foundation.

This stack decision does not start implementation. When coding resumes, use the selected stack and start with a complete sale on one real POS before expanding validation to all four stations.
