# VP3 Viewer Engagement, Notifications & Safety v1

This phase adds the community layer around VP3 Reels without merging viewer identity into customer/license-holder accounts.

## Viewer experience

- Moderated clip comments and one-level replies
- Comment likes, deletion, and reporting
- In-app notifications for replies, comment likes, moderation, and new clips
- Notification preferences
- Viewer blocking
- Creator and show muting
- Safety controls page
- Notification inbox and unread count

Comments require an active, email-verified viewer account. Anonymous visitors may read published comments but cannot post, react, report, or block.

## Creator/customer experience

`account-audience.php` provides aggregate creator, show, and clip metrics:

- Followers
- Views and completion rate
- Likes and saves
- Published comments
- Destination opens

The dashboard does not expose viewer email addresses or private profiles.

## New-clip notification worker

Run periodically:

```bash
php jobs/viewer-notifications-worker.php 20
```

The worker selects approved, rights-confirmed, feed-eligible clips that have not been dispatched, creates bounded in-app notifications for eligible followers, respects mutes and preferences, and records one dispatch ledger row per clip.

## Moderation

`admin/community-moderation.php` provides:

- Open comment report review
- Hide, restore, or remove controls
- Report resolution and dismissal
- Viewer moderation notifications
- Audit logging

## SQL

Existing installations import:

`database/migrations/20260710_viewer_engagement_notifications_safety_v1.sql`

New installations load:

`database/schema-viewer-community.sql`

from the root schema after the Network and viewer identity modules.
