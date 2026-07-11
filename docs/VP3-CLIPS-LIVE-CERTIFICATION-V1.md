# VP3 Clips Live Integration Certification v1

A bridge credential authenticates a licensed Stonefellow installation, but it begins in certification mode. Certification proves the creator runtime can safely render, host, sign, and reconcile clips before VP3 permits real feed publication.

## Source checks

Stonefellow submits signed results for database schema, license receipt, encrypted bridge settings, cURL/TLS, OpenSSL, FFmpeg, FFprobe, private clip storage, public HTTPS base URL, signed context authentication, and a synthetic render probe.

The synthetic video and poster are generated locally and removed after inspection. Source masters are never sent to VP3.

## Central checks

The signed request itself proves the bridge credential, HMAC signature, timestamp, nonce, and request ID. VP3 also validates the license, installation, domain, listing, product identity, and credential status.

## Approval

A passing source report remains in `passed` state until a VP3 administrator approves it. Approval enables live publishing for that exact credential for 180 days. Credential rotation requires a new certification. Withdrawal remains available even after certification expires or is revoked.

## SQL

Import `database/migrations/20260711_vp3_clips_live_certification_v1.sql` after the source bridge migration.
