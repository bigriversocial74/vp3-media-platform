# VP3 Media Group Sales, Licensing & Hosting Platform

Standalone PHP/MySQL business platform for selling, licensing, hosting, installing, updating, and supporting VP3 media products. Stonefellow Membership Platform is the initial catalog product; the architecture supports additional products.

## Foundation v1

- Storytelling-focused public sales website and responsive VP3 visual system
- Product catalog, Stonefellow sales page, hosting, pricing, creative services, demo, contact, and support
- Customer signup/login, email verification, recovery, orders, licenses, hosting, installation progress, downloads, and tickets
- Role-aware admin dashboard for customers, products, plans, orders, licenses, activations, hosting, jobs, releases, support, audit history, and settings
- Secure hashed license keys with safe prefix/fingerprint storage
- Versioned activation, validation, deactivation, update, and release API foundation
- Manual and local simulator hosting providers
- Restartable, locked, event-logged installation jobs
- Modular MySQL schema with foreign keys, constraints, indexes, utf8mb4, webhooks, nonces, audit logs, and security records
- CI, smoke tests, static security audit, and deployment checks

## Requirements

- PHP 8.2+
- PDO MySQL
- MySQL 8.0+ or compatible MariaDB with CHECK/JSON support
- HTTPS in production
- Apache rewrite support for clean API routes, or equivalent Nginx routing

## Installation

1. Copy `config-example.php` to `config.php`.
2. Generate unique values for `security.app_key` and `security.license_pepper`.
3. Create the database and import `database/schema.sql` from the repository root. It loads the operations and security schema modules.
4. Make `var/logs` and `var/locks` writable by the PHP process.
5. Create the first administrator:

   ```bash
   php create-admin.php "David Evans" admin@example.com "a-strong-12-plus-character-password" owner
   ```

6. Open `install.php`, verify readiness, then delete or restrict it.
7. Set `app.url`, secure-cookie behavior, mail, payments, and hosting provider settings for the target environment.

`config.php` is intentionally excluded from version control. Do not overwrite an active production configuration during upgrades.

## License API

- `POST /api/v1/licenses/activate`
- `POST /api/v1/licenses/validate`
- `POST /api/v1/licenses/deactivate`
- `POST /api/v1/licenses/check-updates`
- `GET /api/v1/products/{product_id}/latest-release`

Every request includes a current Unix `timestamp` and unique `nonce`. Activation exchanges the license key for an installation token. Validation, update checks, and deactivation require the installation token. Production traffic requires HTTPS. API responses never expose database IDs, key hashes, internal artifact paths, administrator notes, or other customers.

## Hosting providers

Foundation providers:

- `manual`: creates tracked manual-action receipts
- `local_simulator`: safely simulates provider operations

Implement `VP3\Hosting\HostingProviderInterface` for future cPanel, Plesk, Cloudflare, DigitalOcean, AWS, or other infrastructure providers. Do not pass browser-controlled values to shell commands.

## Quality commands

```bash
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l
php tests/smoke.php
php tests/static-audit.php
bash tests/http-smoke.sh
```

## SQL

Required for a new environment: import `database/schema.sql` from the repository root. Schedule `database/maintenance.sql` to remove expired nonces and recovery tokens. No production configuration file is included.
