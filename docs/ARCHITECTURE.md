# Architecture

VP3 is organized into four boundaries:

1. **Sales and accounts** — public pages, customer authentication, checkout/order requests, account operations.
2. **Business administration** — products, plans, customers, orders, licensing, hosting, jobs, releases, support, settings.
3. **Domain services** — license issuance/validation and provider-neutral hosting/installation services.
4. **Infrastructure adapters** — database, mail, payment, hosting, DNS, release storage, and deployment providers.

Stonefellow is represented as catalog data (`VP3-STONEFELLOW-001`), not as hardcoded platform logic.

## Trust boundaries

- Customer and admin authorization are separate.
- Full license keys are shown only at issuance and stored as a keyed hash thereafter.
- Installation tokens are stored as hashes.
- API requests use HTTPS, timestamps, unique nonces, rate limits, and scoped installation credentials.
- Internal artifact paths are never returned by customer pages or APIs.
- Hosting adapters receive validated records, not arbitrary shell commands.
- Installation jobs use database transactions and filesystem locks to prevent concurrent execution.


## VP3 Network boundary

The central VP3 platform manages public creator/show identities, public-safe license verification, moderation, discovery feeds, and aggregate network analytics. Creator platforms remain authoritative for source clips, source media, schedules, and the full audience experience.

A clip publication is accepted only from a verified public platform listing whose active license, domain, installation UUID, and opaque installation token match. Approved publications link back to the creator's HTTPS destination.

Admin themes are stored per administrator as `light`, `dark`, or `system`; all admin components consume semantic CSS variables rather than separate duplicated stylesheets.
