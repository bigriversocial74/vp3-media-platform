<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$path = $root . '/create-first-admin.php';
if (!is_file($path)) {
    throw new RuntimeException('Missing create-first-admin.php');
}

$body = (string)file_get_contents($path);
$required = [
    "vp3_method() === 'POST'",
    'vp3_verify_csrf',
    'vp3_rate_limit',
    'password_hash($password, PASSWORD_DEFAULT)',
    "VALUES (?,?,?,'owner','active',NOW(),NOW())",
    'SELECT COUNT(*) FROM admins FOR UPDATE',
    'is_file($lockFile)',
    'http_response_code(404)',
    'Cache-Control: no-store',
    'unset($_SESSION',
];
foreach ($required as $needle) {
    if (stripos($body, $needle) === false) {
        throw new RuntimeException("First-admin setup missing security control: {$needle}");
    }
}

$forbidden = [
    '$_POST[\'role\']',
    '$_REQUEST',
    'extract(',
    'md5(',
    'sha1(',
    'INSERT INTO admins(name,email,password_hash,role,status,created_at,updated_at) VALUES(?,?,?,?,',
];
foreach ($forbidden as $needle) {
    if (stripos($body, $needle) !== false) {
        throw new RuntimeException("First-admin setup contains forbidden pattern: {$needle}");
    }
}

if (substr_count($body, "'owner'") < 1) {
    throw new RuntimeException('Owner role must be fixed by the server.');
}

if (stripos($body, '<select') !== false) {
    throw new RuntimeException('Initial owner setup must not expose role selection.');
}

echo "VP3 first-admin web setup security audit: PASS\n";
