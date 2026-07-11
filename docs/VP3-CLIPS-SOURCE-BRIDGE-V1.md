# VP3 Clips Source Bridge v1

The creator-owned VP3 product remains the system of record for clip editing, rendering, rights confirmation, scheduling, and destination selection. The central VP3 platform receives a public rendition and poster, validates the licensed installation, moderates the publication, distributes eligible clips, and reports network analytics.

## Credential lifecycle

An administrator opens a verified public platform and issues a bridge credential for one active license installation. The bridge ID and secret are shown once. VP3 stores the secret with AES-256-GCM encryption under the application key. Rotation marks the prior credential rotated; revocation immediately blocks it.

The complete product license key is never required by the bridge and is never sent by Stonefellow.

## Signed request contract

Headers:

- `X-VP3-Bridge-ID`
- `X-VP3-Timestamp`
- `X-VP3-Nonce`
- `X-VP3-Request-ID`
- `X-VP3-Signature`

Canonical signature input:

```text
METHOD\nREQUEST_PATH\nTIMESTAMP\nNONCE\nSHA256(RAW_JSON_BODY)
```

The signature is lowercase hexadecimal HMAC-SHA256 using the bridge secret. Timestamps have a five-minute tolerance. Nonces are single-use. Request IDs are idempotent and may be safely retried only with the same request body.

## Endpoints

- `POST /api/v1/clips/bridge/context.php`
- `POST|PUT|DELETE /api/v1/clips/bridge/publications.php`
- `POST /api/v1/clips/bridge/status.php`
- `POST /api/v1/clips/bridge/analytics.php`

## Ownership boundary

VP3 does not receive source masters, editing timelines, or private creator assets. It stores the approved public rendition URL, poster URL, destination, publication state, moderation/rights state, feed placement, views, and engagement. Withdrawals preserve audit and analytics records while removing feed eligibility.

## SQL

Existing installations import:

`database/migrations/20260711_vp3_clips_source_bridge_v1.sql`

New installations import `database/schema-clips-bridge.sql` after `database/schema.sql`.
