<?php
declare(strict_types=1);

return [
    'app' => [
        'name' => 'VP3 Media Group',
        'env' => 'production',
        'debug' => false,
        'url' => 'https://vp3media.com',
        'timezone' => 'America/Phoenix',
        'session_name' => 'vp3_session',
        'session_secure' => true,
        'session_same_site' => 'Lax',
        'trusted_proxies' => [],
    ],
    'database' => [
        'dsn' => 'mysql:host=localhost;dbname=vp3_media;charset=utf8mb4',
        'username' => 'vp3_user',
        'password' => 'replace-me',
        'options' => [],
    ],
    'security' => [
        'app_key' => 'replace-with-64-random-characters',
        'license_pepper' => 'replace-with-a-separate-random-secret',
        'api_clock_skew_seconds' => 300,
        'api_rate_limit_per_minute' => 60,
        'login_attempt_limit' => 5,
        'login_lock_minutes' => 15,
    ],
    'mail' => [
        'from_email' => 'support@vp3media.com',
        'from_name' => 'VP3 Media Group',
        'transport' => 'log',
    ],
    'payments' => [
        'provider' => 'manual',
        'stripe_secret_key' => '',
        'stripe_webhook_secret' => '',
    ],
    'hosting' => [
        'provider' => 'local_simulator',
        'base_domain' => 'vp3media.com',
    ],
];
