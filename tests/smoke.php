<?php
declare(strict_types=1);
putenv('VP3_TEST=1');
require dirname(__DIR__) . '/bootstrap.php';

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
$assert((HostingProviderFactory::make('manual')->provision(['hosting_uuid'=>'test'])['status']??'')==='manual_action_required','Manual hosting provider must pause');
$assert((PaymentProviderFactory::make('manual')->createCheckout(['order_number'=>'TEST'],[],[])['status']??'')==='manual_action_required','Manual payment provider must pause');
$assert(vp3_admin_can(['role'=>'support'],'support.manage'),'Support permission missing');
$assert(!vp3_admin_can(['role'=>'support'],'products.manage'),'Support role is over-privileged');
$assert(vp3_admin_can(['role'=>'billing'],'orders.manage'),'Billing permission missing');
$assert(!vp3_admin_can(['role'=>'billing'],'licenses.manage'),'Billing role is over-privileged');
$assert(vp3_admin_can(['role'=>'owner'],'settings.manage'),'Owner permission missing');
$redacted=vp3_redact(['password'=>'secret','nested'=>['token'=>'x']]);
$assert($redacted['password']==='[REDACTED]','Password redaction failed');
$assert($redacted['nested']['token']==='[REDACTED]','Nested token redaction failed');
$required=['index.php','products.php','product.php','hosting.php','pricing.php','features.php','demo.php','signup.php','login.php','forgot-password.php','reset-password.php','verify-email.php','checkout.php','account.php','account-orders.php','account-licenses.php','account-hosting.php','account-downloads.php','account-support.php','admin/index.php','admin/customers.php','admin/products.php','admin/plans.php','admin/orders.php','admin/licenses.php','admin/hosting.php','admin/installations.php','admin/releases.php','admin/support.php','admin/audit.php','api/v1/licenses/activate.php','api/v1/licenses/validate.php','api/v1/licenses/deactivate.php','api/v1/licenses/check-updates.php','api/v1/products/latest-release.php','src/Payments/WebhookEventService.php','database/schema.sql','.github/workflows/ci.yml'];
foreach($required as $file)$assert(is_file(VP3_ROOT.'/'.$file),'Missing required file: '.$file);
$licenseSource=file_get_contents(VP3_ROOT.'/src/Licensing/LicenseService.php')?:'';
$assert(str_contains($licenseSource,'SELECT id,status,domain FROM license_activations'),'Activation domain regression control missing');
$readme=file_get_contents(VP3_ROOT.'/README.md')?:'';
$assert(str_contains($readme,'GET /api/v1/products/{product_id}/latest-release'),'Latest-release method documentation is incorrect');
if($failures){foreach($failures as $failure)fwrite(STDERR,"FAIL: {$failure}\n");exit(1);}echo "Smoke tests passed.\n";
