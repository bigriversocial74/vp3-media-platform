# VP3 Viewer Accounts & Personalized Reels v1

VP3 viewer accounts are separate from customer/license-holder accounts. A viewer can watch anonymously, then claim eligible browser-session activity when they verify or sign in to a viewer profile.

## Viewer capabilities

- Dedicated signup, verification, sign-in, recovery, remembered devices, profile, privacy, export, and deletion
- Full-screen vertical Reels player with touch, wheel, and keyboard navigation
- For You, Following, Trending, and New feeds
- Likes, saves, creator follows, show follows, shares, reports, destination opens, and watch history
- Saved, liked, following, and history libraries
- Public or private viewer profile

## Personalization signals

For You ranking uses creator/show follows, genre affinity from watch completion and active likes/saves, featured placement, recency, and repeat-view penalties. Hidden clips are excluded. Every candidate must still be published, moderation-approved, rights-confirmed, and feed-eligible.

## Identity boundary

- `customers` remain creator/license-holder business accounts.
- `viewer_accounts` are consumer discovery identities.
- Administrators remain separate.
- Anonymous activity uses a random HttpOnly browser token reduced to an HMAC session hash.
- Product license keys are never part of viewer identity.
- Viewer actions use the `viewer` audit actor without being mixed into customer records.

## SQL

Existing installations import, in order:

1. `database/migrations/20260710_viewer_audit_actor_v1.sql`
2. `database/migrations/20260710_viewer_accounts_personalized_reels_v1.sql`

New installations load `database/schema-viewer-audit.sql` and `database/schema-viewers.sql` from the root schema.
