<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
$pageTitle = 'Launch and own your media experience';
$pageDescription = 'VP3 Media Group combines storytelling support, AI-assisted production management, platform licensing, hosting, and launch services for creators and entertainment brands.';
$bodyClass = 'home-page';
require VP3_ROOT . '/includes/header.php';
?>
<section class="story-hero">
  <div class="story-hero-copy">
    <span class="eyebrow">Storytelling · Creative services · Owned media</span>
    <h1>Launch and own your <span>media experience.</span></h1>
    <p>VP3 helps storytellers, creators, artists, producers, and entertainment brands turn an idea into a living destination—one place for the story, the audience, the releases, and the business behind it.</p>
    <div class="hero-actions"><a class="button" href="signup.php">Start your platform <b>→</b></a><a class="button ghost" href="contact.php">Explore creative services <b>→</b></a></div>
    <div class="hero-trust"><span>Own your brand</span><span>Keep your audience relationship</span><span>Stay on schedule</span></div>
  </div>
  <div class="story-hero-art"><img src="<?= vp3_e(vp3_url('assets/images/site/vp3-hero-platform.svg')) ?>" alt="VP3 media platform shown across laptop, tablet, and mobile devices"></div>
</section>

<section class="narrative-section intro-story">
  <div class="section-kicker">Built for storytellers</div>
  <div class="split-heading"><h2>Your story. Your world. <span>Your audience.</span></h2><p>VP3 brings the creative plan, publishing workflow, audience destination, and operating support together—so your work can become a repeatable experience instead of a collection of disconnected posts.</p></div>
  <div class="story-cards">
    <article><span class="card-number">01</span><h3>Shape the experience</h3><p>Turn a concept, season, album, series, or branded world into a clear audience journey with its own voice and destination.</p></article>
    <article><span class="card-number">02</span><h3>Bring everything together</h3><p>Publish video, music, episodes, memberships, merchandise, updates, and exclusives under one owned brand experience.</p></article>
    <article><span class="card-number">03</span><h3>Build a lasting relationship</h3><p>Control the audience data, access model, release rhythm, and ways fans participate in what comes next.</p></article>
  </div>
</section>

<section class="media-story-section">
  <div class="media-story-image"><img src="<?= vp3_e(vp3_url('assets/images/site/storytelling-creative-support.svg')) ?>" alt="Story development workspace with script, storyboard, and content planning"></div>
  <div class="media-story-copy"><span class="eyebrow">Full-service creative support</span><h2>More than a platform. <span>A creative operating partner.</span></h2><p>From the first concept through release day, VP3 can help organize the story, shape the content, build the campaign, and prepare the audience experience.</p><ul class="experience-list"><li><b>Content writing and story development</b><span>Scripts, episode concepts, show structure, brand voice, landing copy, and release narratives.</span></li><li><b>Creative planning and direction</b><span>Storyboards, content maps, audience journeys, campaign concepts, and production priorities.</span></li><li><b>Launch content and engagement</b><span>Teasers, behind-the-scenes releases, membership offers, announcements, and ongoing fan communication.</span></li></ul><a class="text-link" href="contact.php">Talk to VP3 about your story <b>→</b></a></div>
</section>

<section class="experience-band">
  <div class="section-kicker">What the platform enables</div>
  <div class="split-heading"><h2>More than content. <span>A connected world.</span></h2><p>The script provides the foundation; the experience gives audiences a reason to return, participate, support the work, and follow the story over time.</p></div>
  <div class="experience-grid">
    <article><span>Series</span><h3>Episodic storytelling</h3><p>Publish seasons, chapters, short-form stories, behind-the-scenes content, and evolving narratives.</p></article>
    <article><span>Music</span><h3>Releases with context</h3><p>Give songs, albums, performances, and catalog moments a richer story and a direct audience path.</p></article>
    <article><span>Community</span><h3>Fan participation</h3><p>Create memberships, conversations, perks, contests, updates, and reasons to stay connected.</p></article>
    <article><span>Worlds</span><h3>Immersive brand spaces</h3><p>Build an owned destination that feels like the project—not a generic profile inside someone else’s platform.</p></article>
  </div>
</section>

<section class="media-story-section reverse ai-section">
  <div class="media-story-image"><img src="<?= vp3_e(vp3_url('assets/images/site/ai-production-management.svg')) ?>" alt="AI-assisted production calendar, timeline, milestones, and launch checklist"></div>
  <div class="media-story-copy"><span class="eyebrow">AI-assisted production management</span><h2>Keep the production moving—<span>on time, every time.</span></h2><p>VP3’s management layer helps turn creative ambition into an organized release plan. Intelligent reminders, production milestones, content calendars, and launch-readiness tools keep the work visible and actionable.</p><div class="mini-feature-grid"><article><h3>Plan the content</h3><p>Map episodes, drops, campaigns, and supporting assets.</p></article><article><h3>Track every milestone</h3><p>See what is complete, delayed, blocked, or ready to publish.</p></article><article><h3>Protect the release rhythm</h3><p>Use reminders and AI guidance to keep the audience promise.</p></article><article><h3>Know when you are ready</h3><p>Move through a clear checklist before each launch.</p></article></div></div>
</section>

<section class="ownership-section">
  <div><span class="eyebrow">The business behind the story</span><h2>Own the destination. Choose how it is delivered.</h2><p>Launch with a self-hosted product license or choose a VP3-hosted service with installation, infrastructure, updates, and support managed for you.</p></div>
  <div class="ownership-options"><article><span>Self-hosted</span><h3>Your infrastructure, your control.</h3><p>Licensed software deployed to your server and domain with guided setup and eligible product updates.</p></article><article><span>VP3 hosted</span><h3>Your story, without the server work.</h3><p>A managed hosted account, branded subdomain or custom domain, installation workflow, monitoring, and support.</p></article></div>
  <a class="button secondary" href="pricing.php">Compare launch options</a>
</section>

<section class="final-story-cta"><span class="eyebrow">Ready to build the world around your story?</span><h2>Launch and own your media experience.</h2><p>Your audience should have one place to watch, listen, join, shop, participate, and follow what comes next.</p><div class="hero-actions"><a class="button" href="signup.php">Start your platform</a><a class="button ghost" href="contact.php">Book a conversation</a></div></section>
<?php require VP3_ROOT . '/includes/footer.php'; ?>
