# VP3 Clips Syndication Contract

VP3 Clips is a discovery network, not the source editor. Each licensed creator platform creates, edits, schedules, and owns its clips. The central VP3 service stores a syndicated publication record and routes viewers back to the creator's owned destination.

## Publishing flow

1. A creator selects source media inside their licensed VP3 platform.
2. The platform creates a local clip record, poster, caption, destination, rights declaration, and schedule.
3. The platform sends the publication to `POST /api/v1/clips/publications` with its license and installation credentials.
4. VP3 validates the active license, installation token, authorized domain, public platform listing, creator/show ownership, HTTPS media URLs, clip duration, and rights declaration.
5. The publication enters moderation or is auto-approved only when the public platform record explicitly allows it.
6. Approved, rights-confirmed clips become eligible for VP3 Clips feeds.
7. Status and analytics are returned to the source platform through authenticated POST endpoints.
8. Withdrawal at the source calls `DELETE /api/v1/clips/publications` and removes the item from feeds.

## Authenticated publication fields

Every authenticated request includes:

- `license_key`
- `product_id`
- `domain`
- `installation_uuid`
- `installation_token`
- `source_platform_uuid`
- `timestamp`
- `nonce`

Publication writes also include:

- `source_clip_uuid`
- `source_creator_uuid` and/or `source_show_uuid`
- `title`
- `caption`
- `source_media_url` (HTTPS)
- `poster_url` (HTTPS, optional)
- `destination_url` (HTTPS)
- `duration_seconds`
- `aspect_ratio`
- `rights_confirmed`
- `rights_owner_name`
- `scheduled_at`
- `source_updated_at`
- `feed_eligible`

## Endpoints

- `POST /api/v1/clips/publications` — create or update a source-owned publication
- `DELETE /api/v1/clips/publications` — withdraw a publication
- `POST /api/v1/clips/status` — read moderation and publication status without putting credentials in a URL
- `POST /api/v1/clips/analytics` — read central views and engagement
- `POST /api/v1/clips/view` — public view event
- `POST /api/v1/clips/engage` — public like/save/share/destination event
- `GET /api/v1/feed/clips?feed=featured|trending|new` — public cursor-paginated feed
- `GET /api/v1/public/verify-platform?verification_id=...` — public-safe license verification

## Ownership boundary

VP3 does not convert the central feed into the authoritative media library. Source platform UUIDs, source URLs, update timestamps, and content hashes preserve the ownership boundary. The central system never exposes license hashes, installation-token hashes, customer IDs, activation IP addresses, or billing records.

## Public safety endpoint

`POST /api/v1/clips/report.php` records a rate-limited viewer report for a published clip without exposing private platform credentials. Duplicate reports from the same session are held for 24 hours.
