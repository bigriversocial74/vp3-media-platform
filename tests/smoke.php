<?php
declare(strict_types=1);
putenv('VP3_TEST=1');
require dirname(__DIR__) . '/bootstrap.php';
require_once VP3_ROOT . '/includes/network.php';

use VP3\Hosting\HostingProviderFactory;
use VP3\Licensing\LicenseKey;
use VP3\Payments\PaymentProviderFactory;

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$key = LicenseKey::generate();
$assert(str_starts_with($key, 'VP3-'), 'License key prefix missing');
$assert(substr_count($key, '-') === 4, 'License key format invalid');
$assert(strlen(LicenseKey::hash($key)) === 64, 'License hash must be SHA-256 length');
$assert(strlen(LicenseKey::fingerprint($key)) === 12, 'License fingerprint length invalid');
$assert(vp3_normalize_domain('https://Example.COM/path') === 'example.com', 'Domain normalization failed');
$assert(vp3_hash_token('test') !== vp3_hash_token('other'), 'Token hash collision');
$assert((bool)preg_match('/^[0-9a-f-]{36}$/', vp3_uuid()), 'UUID format invalid');

$simulator = HostingProviderFactory::make('local_simulator');
$result = $simulator->subdomainCheck('artist-name', 'vp3media.com');
$assert(($result['ok'] ?? false) === true, 'Simulator provider failed');
$assert(($result['context']['available'] ?? false) === true, 'Subdomain validation failed');
$manualHosting = HostingProviderFactory::make('manual')->provision(['hosting_uuid' => 'test']);
$assert(($manualHosting['status'] ?? '') === 'manual_action_required', 'Manual hosting provider must pause');
$manualPayment = PaymentProviderFactory::make('manual')->createCheckout(['order_number' => 'TEST'], [], []);
$assert(($manualPayment['status'] ?? '') === 'manual_action_required', 'Manual payment provider must pause');

$assert(vp3_admin_can(['role' => 'support'], 'support.manage'), 'Support permission missing');
$assert(vp3_admin_can(['role' => 'support'], 'clips.moderate'), 'Support clip moderation permission missing');
$assert(!vp3_admin_can(['role' => 'support'], 'network.manage'), 'Support role is over-privileged');
$assert(vp3_admin_can(['role' => 'operations'], 'network.manage'), 'Operations network permission missing');
$assert(vp3_admin_can(['role' => 'billing'], 'orders.manage'), 'Billing permission missing');
$assert(!vp3_admin_can(['role' => 'billing'], 'clips.moderate'), 'Billing role is over-privileged');
$assert(vp3_admin_can(['role' => 'owner'], 'settings.manage'), 'Owner permission missing');

$redacted = vp3_redact(['password' => 'secret', 'nested' => ['token' => 'x']]);
$assert($redacted['password'] === '[REDACTED]', 'Password redaction failed');
$assert($redacted['nested']['token'] === '[REDACTED]', 'Nested token redaction failed');

$demo = vp3_network_demo_data();
$assert(count($demo['shows']) >= 2, 'Network demo shows missing');
$assert(count($demo['creators']) >= 2, 'Network demo creators missing');
$assert(count($demo['clips']) >= 3, 'Network demo clips missing');
$assert(vp3_network_show('stonefellow') !== null, 'Network show fallback failed');
$assert(vp3_network_creator('roger-huston') !== null, 'Network creator fallback failed');
$assert(vp3_network_verify('VP3-VRF-STONE001') !== null, 'Public verification fallback failed');

$required = [
    'index.php','products.php','product.php','hosting.php','pricing.php','features.php','demo.php',
    'network.php','shows.php','show.php','creators.php','creator.php','clips.php','clip.php','licenses.php','verify-platform.php',
    'signup.php','login.php','forgot-password.php','reset-password.php','verify-email.php','checkout.php',
    'account.php','account-orders.php','account-licenses.php','account-hosting.php','account-downloads.php','account-network.php','account-support.php',
    'admin/index.php','admin/customers.php','admin/products.php','admin/plans.php','admin/orders.php',
    'admin/licenses.php','admin/hosting.php','admin/installations.php','admin/releases.php','admin/support.php','admin/audit.php',
    'admin/creators.php','admin/creator.php','admin/shows.php','admin/show.php','admin/clips.php','admin/clip.php',
    'admin/public-listings.php','admin/public-listing.php','admin/theme.php',
    'api/v1/licenses/activate.php','api/v1/licenses/validate.php','api/v1/licenses/deactivate.php',
    'api/v1/licenses/check-updates.php','api/v1/products/latest-release.php',
    'api/v1/clips/publications.php','api/v1/clips/status.php','api/v1/clips/analytics.php',
    'api/v1/clips/view.php','api/v1/clips/engage.php','api/v1/clips/report.php','api/v1/feed/clips.php','api/v1/public/verify-platform.php',
    'src/Network/ClipSyndicationService.php','src/Network/FeedService.php','src/Network/PublicLicenseService.php',
    'src/Payments/WebhookEventService.php','database/schema.sql','database/schema-network.sql',
    'database/migrations/20260710_network_clips_v1.sql','.github/workflows/ci.yml',
];
foreach ($required as $file) $assert(is_file(VP3_ROOT . '/' . $file), 'Missing required file: ' . $file);

$licenseSource = file_get_contents(VP3_ROOT . '/src/Licensing/LicenseService.php') ?: '';
$assert(str_contains($licenseSource, 'SELECT id,status,domain FROM license_activations'), 'Activation domain regression control missing');
$clipSource = file_get_contents(VP3_ROOT . '/src/Network/ClipSyndicationService.php') ?: '';
foreach (['source_platform_uuid','installation_token','rights_status','httpsUrl','source_clip_uuid','stale_source_update','recordReport','textSlice'] as $needle) {
    $assert(str_contains($clipSource, $needle), 'Clip syndication control missing: ' . $needle);
}
$readme = file_get_contents(VP3_ROOT . '/README.md') ?: '';
$assert(str_contains($readme, 'GET /api/v1/products/{product_id}/latest-release'), 'Latest-release method documentation is incorrect');
$assert(str_contains($readme, 'POST /api/v1/clips/status'), 'Clip status credentials must not use GET URLs');
$assert(str_contains($readme, 'POST /api/v1/clips/report.php'), 'Clip report endpoint documentation missing');

if ($failures) {
    foreach ($failures as $failure) fwrite(STDERR, "FAIL: {$failure}\n");
    exit(1);
}
echo "Smoke tests passed.\n";
