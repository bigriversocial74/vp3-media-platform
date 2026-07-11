<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'create-first-admin.php' => [
        "SELECT COUNT(*) FROM admins",
        'vp3_verify_csrf',
        'vp3_rate_limit',
        'password_hash',
        "'owner'",
        "'active'",
        'FOR UPDATE',
        'beginTransaction',
        'first-admin-created.lock',
        "admin/login.php",
        'vp3_first_admin_unavailable',
    ],
    'install.php' => [
        'First administrator created',
        'create-first-admin.php',
        'database/vp3-media-platform-initial-install.sql',
    ],
];

foreach ($checks as $file => $needles) {
    $path = $root . '/' . $file;
    if (!is_file($path)) {
        throw new RuntimeException("Missing {$file}");
    }
    $body = (string)file_get_contents($path);
    foreach ($needles as $needle) {
        if (stripos($body, $needle) === false) {
            throw new RuntimeException("{$file} missing {$needle}");
        }
    }
}

echo "VP3 first-admin web setup smoke: PASS\n";
