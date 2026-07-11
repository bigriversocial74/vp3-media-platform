<?php
declare(strict_types=1);

function vp3_csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['_csrf'];
}

function vp3_csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . vp3_e(vp3_csrf_token()) . '">';
}

function vp3_verify_csrf(): void
{
    $provided = (string)($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($provided === '' || !hash_equals(vp3_csrf_token(), $provided)) {
        http_response_code(419);
        exit('Security token validation failed.');
    }
}

function vp3_rate_limit(string $bucket, int $limit, int $windowSeconds): bool
{
    $key = hash('sha256', $bucket . '|' . vp3_client_ip());
    $file = VP3_ROOT . '/var/locks/rate-' . $key . '.json';
    $now = time();
    $handle = fopen($file, 'c+');
    if ($handle === false) {
        vp3_log('error', 'Rate-limit storage unavailable', ['bucket' => $bucket]);
        return false;
    }
    try {
        if (!flock($handle, LOCK_EX)) {
            return false;
        }
        $raw = stream_get_contents($handle) ?: '';
        $decoded = json_decode($raw, true);
        $state = is_array($decoded) ? $decoded : ['start' => $now, 'count' => 0];
        if (($now - (int)($state['start'] ?? 0)) >= $windowSeconds) {
            $state = ['start' => $now, 'count' => 0];
        }
        $state['count'] = (int)($state['count'] ?? 0) + 1;
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($state, JSON_THROW_ON_ERROR));
        fflush($handle);
        return (int)$state['count'] <= $limit;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function vp3_secure_token(int $bytes = 32): string
{
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
}

function vp3_hash_token(string $token): string
{
    return hash_hmac('sha256', $token, (string)vp3_config('security.app_key'));
}

function vp3_normalize_domain(string $domain): string
{
    $domain = strtolower(trim($domain));
    $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
    $domain = preg_replace('#/.*$#', '', $domain) ?? $domain;
    return rtrim($domain, '.');
}

function vp3_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function vp3_require_https_for_api(): void
{
    if ((string)vp3_config('app.env') === 'production' && !vp3_is_https()) {
        vp3_json(['ok' => false, 'error' => ['code' => 'https_required', 'message' => 'HTTPS is required.']], 400);
    }
}
