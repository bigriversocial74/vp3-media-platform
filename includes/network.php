<?php
declare(strict_types=1);

function vp3_network_demo_data(): array
{
    return [
        'creators' => [
            [
                'creator_uuid' => '11111111-1111-4111-8111-111111111111',
                'slug' => 'roger-huston',
                'display_name' => 'Roger Huston',
                'headline' => 'Independent artist, songwriter, and visual storyteller',
                'bio' => 'Original music, performance films, and behind-the-scenes stories built as one connected audience experience.',
                'avatar_url' => '',
                'cover_url' => '',
                'verification_status' => 'verified',
                'featured_rank' => 1,
                'show_count' => 2,
                'clip_count' => 4,
            ],
            [
                'creator_uuid' => '22222222-2222-4222-8222-222222222222',
                'slug' => 'desertrio-studios',
                'display_name' => 'DesertRio Studios',
                'headline' => 'Arizona stories with a cinematic, unscripted edge',
                'bio' => 'A creator-led studio developing reality-style comedy, microdrama, and desert lifestyle entertainment.',
                'avatar_url' => '',
                'cover_url' => '',
                'verification_status' => 'verified',
                'featured_rank' => 2,
                'show_count' => 1,
                'clip_count' => 3,
            ],
        ],
        'shows' => [
            [
                'show_uuid' => '33333333-3333-4333-8333-333333333333',
                'slug' => 'stonefellow',
                'title' => 'Stonefellow',
                'short_description' => 'Music, episodes, performances, membership, and the evolving world behind the band.',
                'description' => 'Stonefellow brings original music, character-driven episodes, live performance, and fan membership into a single owned experience.',
                'show_type' => 'music_series',
                'genre' => 'Rock · Music · Series',
                'cover_url' => '',
                'hero_url' => '',
                'destination_url' => '#',
                'verification_status' => 'verified',
                'featured_rank' => 1,
                'creator_name' => 'Roger Huston',
                'creator_slug' => 'roger-huston',
                'clip_count' => 4,
            ],
            [
                'show_uuid' => '44444444-4444-4444-8444-444444444444',
                'slug' => 'desertrio',
                'title' => 'DesertRio',
                'short_description' => 'An Arizona reality-style scripted comedy with desert heat, poolside stories, and unexpected turns.',
                'description' => 'DesertRio is a light, stylish microdrama experience blending comedy, reality energy, and character-driven Arizona stories.',
                'show_type' => 'microdrama',
                'genre' => 'Microdrama · Comedy · Reality',
                'cover_url' => '',
                'hero_url' => '',
                'destination_url' => '#',
                'verification_status' => 'verified',
                'featured_rank' => 2,
                'creator_name' => 'DesertRio Studios',
                'creator_slug' => 'desertrio-studios',
                'clip_count' => 3,
            ],
        ],
        'clips' => [
            [
                'publication_uuid' => '55555555-5555-4555-8555-555555555551',
                'title' => 'Beyond the lights',
                'caption' => 'A first look at the performance world behind Stonefellow.',
                'poster_url' => '',
                'source_media_url' => '',
                'destination_url' => '#',
                'duration_seconds' => 38,
                'published_at' => date('Y-m-d H:i:s'),
                'creator_name' => 'Roger Huston',
                'creator_slug' => 'roger-huston',
                'show_title' => 'Stonefellow',
                'show_slug' => 'stonefellow',
                'view_count' => 12840,
                'engagement_count' => 912,
            ],
            [
                'publication_uuid' => '55555555-5555-4555-8555-555555555552',
                'title' => 'Poolside arrival',
                'caption' => 'The weekend starts quietly. It does not stay that way.',
                'poster_url' => '',
                'source_media_url' => '',
                'destination_url' => '#',
                'duration_seconds' => 27,
                'published_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'creator_name' => 'DesertRio Studios',
                'creator_slug' => 'desertrio-studios',
                'show_title' => 'DesertRio',
                'show_slug' => 'desertrio',
                'view_count' => 8640,
                'engagement_count' => 604,
            ],
            [
                'publication_uuid' => '55555555-5555-4555-8555-555555555553',
                'title' => 'The sound before the show',
                'caption' => 'Thirty seconds from rehearsal to stage.',
                'poster_url' => '',
                'source_media_url' => '',
                'destination_url' => '#',
                'duration_seconds' => 31,
                'published_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
                'creator_name' => 'Roger Huston',
                'creator_slug' => 'roger-huston',
                'show_title' => 'Stonefellow',
                'show_slug' => 'stonefellow',
                'view_count' => 5920,
                'engagement_count' => 431,
            ],
        ],
        'listings' => [
            [
                'listing_uuid' => '66666666-6666-4666-8666-666666666661',
                'slug' => 'stonefellow-official',
                'display_name' => 'Stonefellow Official Platform',
                'description' => 'Official music, video, membership, and merchandise experience.',
                'public_domain' => 'stonefellow.example.com',
                'hosting_type' => 'vp3_hosted',
                'verification_id' => 'VP3-VRF-STONE001',
                'verification_status' => 'verified',
                'product_name' => 'Stonefellow Membership Platform',
                'edition' => 'Creator',
                'launched_at' => date('Y-m-d', strtotime('-30 days')),
            ],
        ],
    ];
}

function vp3_network_db_ready(): bool
{
    static $ready;
    if ($ready === null) {
        $ready = vp3_db_available();
    }
    return $ready;
}

function vp3_network_query(string $sql, array $params = []): array
{
    if (!vp3_network_db_ready()) {
        return [];
    }
    try {
        $stmt = vp3_db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        vp3_log('warning', 'Network query failed', ['message' => $e->getMessage()]);
        return [];
    }
}

function vp3_network_creators(int $limit = 24): array
{
    $rows = vp3_network_query(
        "SELECT c.creator_uuid,c.slug,c.display_name,c.headline,c.bio,c.avatar_url,c.cover_url,c.verification_status,c.featured_rank,
                COUNT(DISTINCT sc.show_id) AS show_count,COUNT(DISTINCT cp.id) AS clip_count
         FROM creators c
         LEFT JOIN show_creators sc ON sc.creator_id=c.id
         LEFT JOIN clip_publications cp ON cp.creator_id=c.id AND cp.publication_status='published'
         WHERE c.listing_status='published'
         GROUP BY c.id
         ORDER BY c.featured_rank>0 DESC,c.featured_rank ASC,c.display_name ASC
         LIMIT " . max(1, min($limit, 100))
    );
    return $rows ?: (vp3_network_db_ready() ? [] : vp3_network_demo_data()['creators']);
}

function vp3_network_creator(string $slug): ?array
{
    $rows = vp3_network_query(
        "SELECT c.* FROM creators c WHERE c.slug=? AND c.listing_status='published' LIMIT 1",
        [$slug]
    );
    $creator = $rows[0] ?? null;
    if (!$creator && !vp3_network_db_ready()) {
        foreach (vp3_network_demo_data()['creators'] as $candidate) {
            if ($candidate['slug'] === $slug) {
                $creator = $candidate;
                break;
            }
        }
    }
    if (!$creator) {
        return null;
    }
    $creator['shows'] = array_values(array_filter(vp3_network_shows(100), static fn(array $show): bool => ($show['creator_slug'] ?? '') === $slug));
    $creator['clips'] = array_values(array_filter(vp3_network_clips(100), static fn(array $clip): bool => ($clip['creator_slug'] ?? '') === $slug));
    return $creator;
}

function vp3_network_shows(int $limit = 24): array
{
    $rows = vp3_network_query(
        "SELECT s.show_uuid,s.slug,s.title,s.short_description,s.description,s.show_type,s.genre,s.cover_url,s.hero_url,s.destination_url,
                s.verification_status,s.featured_rank,c.display_name AS creator_name,c.slug AS creator_slug,
                COUNT(DISTINCT cp.id) AS clip_count
         FROM shows s
         LEFT JOIN show_creators sc ON sc.show_id=s.id AND sc.is_primary=1
         LEFT JOIN creators c ON c.id=sc.creator_id
         LEFT JOIN clip_publications cp ON cp.show_id=s.id AND cp.publication_status='published'
         WHERE s.status='published'
         GROUP BY s.id,c.id
         ORDER BY s.featured_rank>0 DESC,s.featured_rank ASC,s.title ASC
         LIMIT " . max(1, min($limit, 100))
    );
    return $rows ?: (vp3_network_db_ready() ? [] : vp3_network_demo_data()['shows']);
}

function vp3_network_show(string $slug): ?array
{
    $show = null;
    foreach (vp3_network_shows(100) as $candidate) {
        if (($candidate['slug'] ?? '') === $slug) {
            $show = $candidate;
            break;
        }
    }
    if (!$show) {
        return null;
    }
    $show['clips'] = array_values(array_filter(vp3_network_clips(100), static fn(array $clip): bool => ($clip['show_slug'] ?? '') === $slug));
    return $show;
}

function vp3_network_clips(int $limit = 24, string $feed = 'featured'): array
{
    $order = match ($feed) {
        'trending' => '(COALESCE(v.views,0)+(COALESCE(e.engagements,0)*4)) DESC,cp.published_at DESC',
        'new' => 'cp.published_at DESC',
        default => 'cp.featured_rank>0 DESC,cp.featured_rank ASC,cp.published_at DESC',
    };
    $rows = vp3_network_query(
        "SELECT cp.publication_uuid,cp.title,cp.caption,cp.poster_url,cp.source_media_url,cp.destination_url,cp.duration_seconds,cp.published_at,
                c.display_name AS creator_name,c.slug AS creator_slug,s.title AS show_title,s.slug AS show_slug,
                COALESCE(v.views,0) AS view_count,COALESCE(e.engagements,0) AS engagement_count
         FROM clip_publications cp
         LEFT JOIN creators c ON c.id=cp.creator_id
         LEFT JOIN shows s ON s.id=cp.show_id
         LEFT JOIN (SELECT clip_publication_id,COUNT(*) views FROM clip_view_events GROUP BY clip_publication_id) v ON v.clip_publication_id=cp.id
         LEFT JOIN (SELECT clip_publication_id,COUNT(*) engagements FROM clip_engagement_events GROUP BY clip_publication_id) e ON e.clip_publication_id=cp.id
         WHERE cp.publication_status='published' AND cp.moderation_status='approved' AND cp.rights_status='confirmed' AND cp.feed_eligible=1
         ORDER BY {$order}
         LIMIT " . max(1, min($limit, 100))
    );
    return $rows ?: (vp3_network_db_ready() ? [] : array_slice(vp3_network_demo_data()['clips'], 0, $limit));
}

function vp3_network_clip(string $uuid): ?array
{
    foreach (vp3_network_clips(100) as $clip) {
        if (($clip['publication_uuid'] ?? '') === $uuid) {
            return $clip;
        }
    }
    return null;
}

function vp3_network_listings(int $limit = 50): array
{
    $rows = vp3_network_query(
        "SELECT ppl.listing_uuid,ppl.slug,ppl.display_name,ppl.description,ppl.public_domain,ppl.hosting_type,ppl.verification_id,
                ppl.verification_status,ppl.launched_at,p.name AS product_name,l.edition
         FROM public_platform_listings ppl
         JOIN licenses l ON l.id=ppl.license_id
         JOIN products p ON p.id=ppl.product_id
         WHERE ppl.listing_status='published' AND ppl.verification_status='verified'
         ORDER BY ppl.featured_rank>0 DESC,ppl.featured_rank ASC,ppl.display_name ASC
         LIMIT " . max(1, min($limit, 100))
    );
    return $rows ?: (vp3_network_db_ready() ? [] : vp3_network_demo_data()['listings']);
}

function vp3_network_verify(string $verificationId): ?array
{
    $verificationId = strtoupper(trim($verificationId));
    if ($verificationId === '') {
        return null;
    }
    $rows = vp3_network_query(
        "SELECT ppl.listing_uuid,ppl.display_name,ppl.public_domain,ppl.hosting_type,ppl.verification_id,ppl.verification_status,
                ppl.launched_at,p.name AS product_name,l.edition,l.status AS license_status
         FROM public_platform_listings ppl
         JOIN licenses l ON l.id=ppl.license_id
         JOIN products p ON p.id=ppl.product_id
         WHERE ppl.verification_id=? AND ppl.listing_status='published' LIMIT 1",
        [$verificationId]
    );
    if (isset($rows[0])) {
        return $rows[0];
    }
    if (!vp3_network_db_ready()) foreach (vp3_network_demo_data()['listings'] as $listing) {
        if ($listing['verification_id'] === $verificationId) {
            return $listing + ['license_status' => 'active'];
        }
    }
    return null;
}

function vp3_network_placeholder(string $label): string
{
    $initials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]+/', '', $label) ?: 'VP3', 0, 3));
    return '<span class="network-placeholder" aria-hidden="true"><b>' . vp3_e($initials) . '</b><i></i></span>';
}
