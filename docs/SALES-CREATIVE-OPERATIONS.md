# VP3 Sales & Creative Project Operations v1

This phase connects public discovery, sales qualification, proposals, customer onboarding, and managed creative delivery.

## Operating flow

1. A visitor submits the contact form or requests a discovery call.
2. VP3 records one deduplicated lead, source attribution, activity, notes, priority, next action, and demo-request details.
3. Sales qualifies the opportunity and assembles configurable service packages into a proposal.
4. A registered customer reviews and accepts the proposal from their authenticated account. The signer email must match the customer account.
5. VP3 activates a creative project from an accepted customer proposal or an approved project brief.
6. The project workspace manages milestones, VP3 and customer tasks, dependencies, shared comments, HTTPS asset references, approvals, readiness, activity, and production-plan drafts.
7. Customer notifications surface proposals, assignments, approvals, and project updates.

## Ownership and authorization

- Leads, proposals, project briefs, projects, creators, and shows are linked through validated customer boundaries.
- A task, milestone, asset, approval, or production-plan item cannot reference records from another project.
- Customers can see only their projects and proposals.
- Customers can update only tasks assigned to them and cannot complete a task until its dependency is complete.
- Proposal acceptance requires an authenticated customer and an email matching that account.
- Submitted or approved briefs become read-only until VP3 requests a revision.
- Administrator permissions separate sales, project operations, creative contributors, billing, support, hosting operations, and system ownership.

## Asset boundary

Foundation v1 stores secure asset metadata and validated HTTPS references. It does not claim that binary uploads, object storage, antivirus scanning, transcoding, or CDN delivery are configured. Those capabilities should be implemented through a storage-provider adapter in a later phase.

## AI production boundary

The production-plan tables and workflow are ready for AI-assisted planning, but this phase does not automatically call a model or publish AI output. Future AI suggestions must remain drafts until a customer or authorized VP3 staff member approves them.

## SQL

Existing installations:

```text
database/migrations/20260711_sales_creative_operations_v1.sql
```

New installations:

```text
database/schema.sql
```
