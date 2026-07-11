<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$lockFile = VP3_ROOT . '/var/locks/first-admin-created.lock';
$created = false;
$error = '';
$name = trim((string)($_POST['name'] ?? 'David Evans'));
$email = strtolower(trim((string)($_POST['email'] ?? '')));

function vp3_first_admin_unavailable(): never
{
    http_response_code(404);
    exit;
}

if (is_file($lockFile) || !vp3_db_available()) {
    vp3_first_admin_unavailable();
}

try {
    $adminCount = (int)vp3_db()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
} catch (Throwable $e) {
    vp3_log('warning', 'First-admin setup unavailable', ['reason' => 'admins_table_unavailable']);
    vp3_first_admin_unavailable();
}

if ($adminCount > 0) {
    vp3_first_admin_unavailable();
}

if (vp3_method() === 'POST') {
    vp3_verify_csrf();

    if (!vp3_rate_limit('first-admin-create', 5, 3600)) {
        $error = 'Too many setup attempts. Wait before trying again.';
    } else {
        $password = (string)($_POST['password'] ?? '');
        $confirmation = (string)($_POST['password_confirmation'] ?? '');

        if ($name === '' || strlen($name) > 150) {
            $error = 'Enter a name between 1 and 150 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $error = 'Enter a valid email address.';
        } elseif (strlen($password) < 12) {
            $error = 'Use a password containing at least 12 characters.';
        } elseif ($password !== $confirmation) {
            $error = 'The passwords do not match.';
        } elseif (!is_dir(dirname($lockFile)) || !is_writable(dirname($lockFile))) {
            $error = 'The var/locks directory must be writable before creating the owner account.';
        } else {
            $db = vp3_db();
            $db->beginTransaction();

            try {
                $countStatement = $db->query('SELECT COUNT(*) FROM admins FOR UPDATE');
                if ((int)$countStatement->fetchColumn() > 0 || is_file($lockFile)) {
                    throw new RuntimeException('first_admin_already_exists');
                }

                $statement = $db->prepare(
                    "INSERT INTO admins
                     (name,email,password_hash,role,status,created_at,updated_at)
                     VALUES (?,?,?,'owner','active',NOW(),NOW())"
                );
                $statement->execute([
                    $name,
                    $email,
                    password_hash($password, PASSWORD_DEFAULT),
                ]);
                $adminId = (int)$db->lastInsertId();
                $db->commit();

                $lockWritten = file_put_contents(
                    $lockFile,
                    'First administrator created at ' . gmdate('c') . PHP_EOL,
                    LOCK_EX
                );
                if ($lockWritten === false) {
                    vp3_log('error', 'First-admin lock file could not be written', ['admin_id' => $adminId]);
                }

                try {
                    vp3_audit('admin', $adminId, 'admin.first_owner_created', 'admin', (string)$adminId);
                } catch (Throwable $auditError) {
                    vp3_log('warning', 'First-admin audit record failed', ['admin_id' => $adminId]);
                }

                unset($_SESSION['_csrf']);
                $created = true;
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                if ($e instanceof RuntimeException && $e->getMessage() === 'first_admin_already_exists') {
                    vp3_first_admin_unavailable();
                }
                vp3_log('error', 'First-admin creation failed', ['type' => $e::class]);
                $error = 'The owner account could not be created. Verify the database and try again.';
            }
        }
    }
}

$pageTitle = 'Create First Administrator';
$pageDescription = 'Create the one-time owner account for this VP3 Media Platform installation.';
$bodyClass = 'first-admin-setup';
require VP3_ROOT . '/includes/header.php';
?>
<section class="section dark">
    <div class="section-head">
        <span class="eyebrow">VP3 Initial Setup</span>
        <h2><?= $created ? 'Owner account created.' : 'Create the first administrator.' ?></h2>
        <p><?= $created ? 'Your one-time setup is complete. The browser setup page is now disabled.' : 'This page only works while the administrators table is empty. The account is always created with the owner role.' ?></p>
    </div>
</section>
<section class="section">
    <div class="card" style="max-width:680px;margin:0 auto;">
        <?php if ($created): ?>
            <h3>Setup complete</h3>
            <p>You can now sign in to the VP3 administration area with the email and password you entered.</p>
            <p><a class="button" href="<?= vp3_e(vp3_url('admin/login.php')) ?>">Open administrator login</a></p>
            <p class="helper">For additional safety, delete or restrict <code>install.php</code> after completing the environment checks.</p>
        <?php else: ?>
            <h3>Owner account</h3>
            <p class="helper">Use a unique password of at least 12 characters. No role selection is provided because initial setup may only create an owner.</p>
            <?php if ($error !== ''): ?><div class="flash error"><?= vp3_e($error) ?></div><?php endif; ?>
            <form method="post" autocomplete="off">
                <?= vp3_csrf_field() ?>
                <label>Name
                    <input type="text" name="name" maxlength="150" value="<?= vp3_e($name) ?>" autocomplete="name" required>
                </label>
                <label>Email
                    <input type="email" name="email" maxlength="190" value="<?= vp3_e($email) ?>" autocomplete="email" required>
                </label>
                <label>Password
                    <input type="password" name="password" minlength="12" autocomplete="new-password" required>
                </label>
                <label>Confirm password
                    <input type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required>
                </label>
                <button class="button" type="submit">Create owner account</button>
            </form>
        <?php endif; ?>
    </div>
</section>
<?php require VP3_ROOT . '/includes/footer.php'; ?>
