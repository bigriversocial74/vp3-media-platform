# VP3 Media Platform Foundation v1

## Summary

Creates the standalone VP3 Media Group business platform for selling, licensing, hosting, installing, updating, and supporting VP3 products. Stonefellow Membership Platform is the first catalog product, while the product, plan, order, license, release, hosting, and provisioning architecture remains multi-product.

## Included

- Storytelling-focused public sales website and responsive VP3 visual system
- Pricing, hosting, products, demo, sign-in, sign-up, verification, and password recovery
- Customer orders, licenses, hosted accounts, installation progress, downloads, and support
- Role-aware administration for customers, products, plans, orders, licenses, hosting, jobs, releases, tickets, audit history, and settings
- Secure hashed-license workflow and versioned activation/validation/deactivation/update API
- Manual and local-simulator hosting adapters with restartable installation jobs
- Modular MySQL schema, maintenance SQL, installer checks, tests, static audit, HTTP smoke tests, and GitHub Actions

## SQL

**SQL required:** import `database/schema.sql` from the repository root. It loads the operations and security schema modules.

## Verification boundary

GitHub Actions must pass before merge. Live Stripe, SMTP, DNS, infrastructure provisioning, production downloads, and deployment are not claimed as configured or tested.
