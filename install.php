<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$databaseConnected = vp3_db_available();
$adminCreated = false;
if ($databaseConnected) {
    try {
        $adminCreated = (int)vp3_db()->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
    } catch (Throwable $e) {
        $adminCreated = false;
    }
}

$environmentChecks = [
    'PHP 8.2+' => version_compare(PHP_VERSION, '8.2.0', '>='),
    'PDO extension' => extension_loaded('pdo'),
    'PDO MySQL extension' => extension_loaded('pdo_mysql'),
    'OpenSSL extension' => extension_loaded('openssl'),
    'JSON extension' => extension_loaded('json'),
    'Config file present' => is_file(VP3_ROOT . '/config.php'),
    'Application key replaced' => (string)vp3_config('security.app_key') !== 'replace-with-64-random-characters',
    'License pepper replaced' => (string)vp3_config('security.license_pepper') !== 'replace-with-a-separate-random-secret',
    'Log directory writable' => is_writable(VP3_ROOT . '/var/logs'),
    'Lock directory writable' => is_writable(VP3_ROOT . '/var/locks'),
    'Database connected' => $databaseConnected,
];
$checks = $environmentChecks + ['First administrator created' => $adminCreated];
$environmentReady = !in_array(false, $environmentChecks, true);
$installationReady = $environmentReady && $adminCreated;

$pageTitle = 'Environment Check';
require VP3_ROOT . '/includes/header.php';
?>
<section class="section dark">
    <div class="section-head">
        <span class="eyebrow">VP3 Foundation v1</span>
        <h2>Environment readiness check.</h2>
        <p>This page verifies configuration, database access, and first-owner setup. It does not expose credentials or run schema changes.</p>
    </div>
</section>
<section class="section">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Requirement</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($checks as $label => $passed): ?>
                <tr><td><?= vp3_e($label) ?></td><td><span class="badge <?= $passed ? 'active' : 'failed' ?>"><?= $passed ? 'Ready' : 'Action required' ?></span></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3><?= $installationReady ? 'Installation ready' : ($environmentReady ? 'Create the first owner account' : 'Complete the remaining requirements') ?></h3>
        <?php if ($environmentReady && !$adminCreated): ?>
            <p>The application and database are ready. Create the first owner through the protected one-time browser page.</p>
            <p><a class="button" href="<?= vp3_e(vp3_url('create-first-admin.php')) ?>">Create first administrator</a></p>
        <?php elseif ($installationReady): ?>
            <p>The environment and first administrator account are ready.</p>
            <p><a class="button" href="<?= vp3_e(vp3_url('admin/login.php')) ?>">Open administrator login</a></p>
        <?php endif; ?>
        <ol>
            <li>Copy <code>config-example.php</code> to <code>config.php</code> and enter the production settings.</li>
            <li>Generate separate application and license secrets.</li>
            <li>Create an empty MySQL database and import <code>database/vp3-media-platform-initial-install.sql</code> from the deployment ZIP.</li>
            <li>When the environment checks pass, open <code>create-first-admin.php</code> to create the owner account.</li>
            <li>Delete or restrict <code>install.php</code> after deployment validation.</li>
        </ol>
    </div>
</section>
<?php require VP3_ROOT . '/includes/footer.php'; ?>
