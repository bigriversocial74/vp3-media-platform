<?php
declare(strict_types=1);

const VP3_ROOT = __DIR__;

$configFile = VP3_ROOT . '/config.php';
if (!is_file($configFile)) {
    $configFile = VP3_ROOT . '/config-example.php';
}
$config = require $configFile;
$GLOBALS['vp3_config'] = $config;

date_default_timezone_set((string)($config['app']['timezone'] ?? 'UTC'));

if (PHP_SAPI !== 'cli') {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'; img-src 'self' data: https:; media-src 'self' https:; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
    if ((string)($config['app']['env'] ?? '') === 'production' && (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    $secure = (bool)($config['app']['session_secure'] ?? true);
    session_name((string)($config['app']['session_name'] ?? 'vp3_session'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => (string)($config['app']['session_same_site'] ?? 'Lax'),
    ]);
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'VP3\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = VP3_ROOT . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

require_once VP3_ROOT . '/includes/functions.php';
require_once VP3_ROOT . '/includes/database.php';
require_once VP3_ROOT . '/includes/security.php';
require_once VP3_ROOT . '/includes/auth.php';
require_once VP3_ROOT . '/includes/providers.php';

set_exception_handler(static function (Throwable $e) use ($config): void {
    vp3_log('error', 'Unhandled exception', ['type' => $e::class, 'message' => $e->getMessage()]);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Application error: {$e->getMessage()}\n");
        exit(1);
    }
    http_response_code(500);
    $debug = (bool)($config['app']['debug'] ?? false);
    echo $debug ? '<pre>' . vp3_e((string)$e) . '</pre>' : 'An unexpected error occurred.';
});
