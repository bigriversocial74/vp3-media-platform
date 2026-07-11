# VP3 Media Group Sales, Licensing & Hosting Platform

Standalone PHP/MySQL business platform for selling, licensing, hosting, installing, updating, and supporting VP3 media products. Stonefellow Membership Platform is the initial catalog product; the architecture supports additional products.

## Foundation v1

- Public SaaS sales website with responsive VP3 visual system
- Product catalog, Stonefellow sales page, hosting, pricing, features, demo, contact, and support
- Customer signup/login, orders, licenses, hosting, installation progress, downloads, and tickets
- Admin dashboard for customers, products, plans, orders, licenses, activations, hosting, jobs, releases, support, and settings
- Secure hashed license keys with safe prefix/fingerprint storage
- Versioned activation, validation, deactivation, update, and release API foundation
- Manual and local simulator hosting providers
- Restartable, locked, event-logged installation jobs
- MySQL schema with foreign keys, constraints, indexes, utf8mb4, webhooks, nonces, audit logs, and security records
- CI, smoke tests, static security audit, and deployment checks


## VP3 Network and Clips Foundation v1

- Public directories for current shows, creators, clips, and verified licensed platforms
- Public-safe verification IDs that never expose license keys, hashes, internal IDs, activations, IP addresses, or billing data
- Source-owned clip syndication: clips are created in licensed creator platforms and published into VP3 Clips
- License- and installation-authorized publication upsert, withdrawal, status, and analytics APIs
- Moderation, rights declarations, reports, feed eligibility, featured placement, view events, and engagement events
- Admin creator/show/platform management with light, dark, and system appearance modes
- Modular `database/schema-network.sql` and existing-install migration

See `docs/CLIPS-SYNDICATION.md` for the source-platform contract.

## VP3 Sales & Creative Project Operations v1

- Public creative-services presentation, contact lead capture, and discovery-call requests
- Lead pipeline with qualification stages, source attribution, priorities, notes, activities, and follow-up dates
- Configurable service packages and proposal line items
- Authenticated proposal review and acceptance with account-email verification
- Structured customer project briefs and managed project activation
- Customer and administrator project dashboards
- Milestones, dependent tasks, assignments, approvals, shared comments, activity history, and readiness scoring
- Secure HTTPS asset-reference library foundation
- Draft production plans and future AI-management data boundaries
- Customer notifications and expanded sales/project/creative staff permissions

See `docs/SALES-CREATIVE-OPERATIONS.md` for the workflow and trust boundaries.

## Requirements

- PHP 8.2+
- PDO MySQL
- MySQL 8.0+ or compatible MariaDB with CHECK/JSON support
- HTTPS in production
- Apache rewrite support for clean API routes, or equivalent Nginx routing

## Installation

1. Copy `config-example.php` to `config.php`.
2. Generate unique values for `security.app_key` and `security.license_pepper`.
3. Create the database and import `database/schema.sql`. Existing installations should run applicable migrations in order: `database/migrations/20260710_network_clips_v1.sql`, then `database/migrations/20260711_sales_creative_operations_v1.sql`.
4. Make `var/logs` and `var/locks` writable by the PHP process.
5. Create the first administrator:

   ```bash
   php create-admin.php "David Evans" admin@example.com "a-strong-12-plus-character-password" owner
   ```

6. Open `install.php`, verify readiness, then delete or restrict it.
7. Set `app.url`, secure-cookie behavior, mail, payments, and hosting provider settings for the target environment.

`config.php` is intentionally excluded from version control. Do not overwrite an active production configuration during upgrades.

## License API

Clean routes:

- `POST /api/v1/licenses/activate`
- `POST /api/v1/licenses/validate`
- `POST /api/v1/licenses/deactivate`
- `POST /api/v1/licenses/check-updates`
- `GET /api/v1/products/{product_id}/latest-release`

Every request includes a current Unix `timestamp` and unique `nonce`. Activation exchanges the license key for an installation token. Validation, update checks, and deactivation require the installation token. Production traffic requires HTTPS. API responses never expose database IDs, key hashes, internal artifact paths, administrator notes, or other customers.

## VP3 Clips API

- `POST /api/v1/clips/publications`
- `DELETE /api/v1/clips/publications`
- `POST /api/v1/clips/status`
- `POST /api/v1/clips/analytics`
- `POST /api/v1/clips/view`
- `POST /api/v1/clips/engage`
- `POST /api/v1/clips/report.php`
- `GET /api/v1/feed/clips`
- `GET /api/v1/public/verify-platform`

Status and analytics use POST so private license and installation credentials are never placed in URLs. Public feed and verification endpoints are rate limited and return only public-safe fields.

## Hosting providers

Foundation providers:

- `manual`: creates tracked manual-action receipts
- `local_simulator`: safely simulates provider operations

Implement `VP3\Hosting\HostingProviderInterface` for future cPanel, Plesk, Cloudflare, DigitalOcean, AWS, or other infrastructure providers. Do not pass browser-controlled values to shell commands.

## Installation jobs

Jobs are lock-protected, idempotent at the queue boundary, restartable after failure, event logged, and visible to admins and customers. Foundation v1 intentionally simulates infrastructure actions. Live provisioning should be added through authenticated provider SDKs and constrained workers.

## Payment boundary

Checkout creates pending orders through provider-neutral business logic. Stripe credentials, Checkout Sessions, subscriptions, signed webhooks, and reconciliation must be configured and tested before claiming live payment readiness.

## Quality commands

```bash
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l
php tests/smoke.php
php tests/static-audit.php
bash tests/http-smoke.sh
```

## SQL

Required: import `database/schema.sql` once for a new environment. Schedule `database/maintenance.sql` to remove expired nonces and recovery tokens. No production configuration file is included.
