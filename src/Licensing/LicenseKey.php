<?php
declare(strict_types=1);
namespace VP3\Licensing;

final class LicenseKey
{
    public static function generate(): string
    {
        $parts = [];
        for ($i = 0; $i < 4; $i++) {
            $parts[] = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        }
        return 'VP3-' . implode('-', $parts);
    }

    public static function hash(string $key): string
    {
        return hash_hmac('sha256', strtoupper(trim($key)), (string)\vp3_config('security.license_pepper'));
    }

    public static function prefix(string $key): string
    {
        return substr(strtoupper(trim($key)), 0, 12);
    }

    public static function fingerprint(string $key): string
    {
        return strtoupper(substr(hash('sha256', strtoupper(trim($key))), 0, 12));
    }
}
