<?php
declare(strict_types=1);

function vp3_config(?string $path = null, mixed $default = null): mixed
{
    $config = $GLOBALS['vp3_config'] ?? [];
    if ($path === null || $path === '') {
        return $config;
    }
    foreach (explode('.', $path) as $segment) {
        if (!is_array($config) || !array_key_exists($segment, $config)) {
            return $default;
        }
        $config = $config[$segment];
    }
    return $config;
}

function vp3_e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function vp3_url(string $path = ''): string
{
    $base = rtrim((string)vp3_config('app.url', ''), '/');
    return $base . '/' . ltrim($path, '/');
}

function vp3_redirect(string $path): never
{
    header('Location: ' . vp3_url($path), true, 302);
    exit;
}

function vp3_method(): string
{
    return strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
}

function vp3_input(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_scalar($value) ? trim((string)$value) : $default;
}

function vp3_json_input(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function vp3_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function vp3_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return sprintf('%s-%s-%s-%s-%s', substr($hex,0,8), substr($hex,8,4), substr($hex,12,4), substr($hex,16,4), substr($hex,20));
}

function vp3_money(int $cents, string $currency = 'USD'): string
{
    return number_format($cents / 100, 2) . ' ' . strtoupper($currency);
}

function vp3_flash(string $key, ?string $message = null): ?string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return null;
    }
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return is_string($value) ? $value : null;
}

function vp3_log(string $level, string $message, array $context = []): void
{
    $redacted = vp3_redact($context);
    $record = json_encode([
        'time' => gmdate('c'),
        'level' => $level,
        'message' => $message,
        'context' => $redacted,
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
    $path = VP3_ROOT . '/var/logs/app-' . gmdate('Y-m-d') . '.log';
    @file_put_contents($path, $record, FILE_APPEND | LOCK_EX);
}

function vp3_redact(array $data): array
{
    $blocked = ['password','password_hash','license_key','license_key_hash','secret','token','authorization'];
    foreach ($data as $key => $value) {
        if (in_array(strtolower((string)$key), $blocked, true)) {
            $data[$key] = '[REDACTED]';
        } elseif (is_array($value)) {
            $data[$key] = vp3_redact($value);
        }
    }
    return $data;
}

function vp3_client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}


function vp3_audit(string $actorType, ?int $actorId, string $action, ?string $entityType = null, ?string $entityUuid = null, array $metadata = []): void
{
    if (!vp3_db_available()) {
        return;
    }
    try {
        $stmt = vp3_db()->prepare('INSERT INTO audit_logs (actor_type,actor_id,action,entity_type,entity_uuid,ip_address,metadata_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
        $stmt->execute([$actorType,$actorId,$action,$entityType,$entityUuid,vp3_client_ip(),$metadata ? json_encode(vp3_redact($metadata), JSON_THROW_ON_ERROR) : null]);
    } catch (Throwable $e) {
        vp3_log('error', 'Audit write failed', ['action'=>$action,'message'=>$e->getMessage()]);
    }
}

function vp3_public_https_url(mixed $value, string $fallback = ''): string
{
    $url = trim((string)$value);
    if ($url === '') {
        return $fallback;
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return $fallback;
    }
    return strtolower((string)parse_url($url, PHP_URL_SCHEME)) === 'https' ? $url : $fallback;
}
