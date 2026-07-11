<?php
declare(strict_types=1);

function vp3_customer(): ?array
{
    $id = (int)($_SESSION['customer_id'] ?? 0);
    if ($id < 1 || !vp3_db_available()) {
        return null;
    }
    $stmt = vp3_db()->prepare('SELECT id, customer_uuid, name, company_name, email, status FROM customers WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return is_array($row) && $row['status'] === 'active' ? $row : null;
}

function vp3_admin(): ?array
{
    $id = (int)($_SESSION['admin_id'] ?? 0);
    if ($id < 1 || !vp3_db_available()) {
        return null;
    }
    $stmt = vp3_db()->prepare('SELECT id, name, email, role, status, theme_preference FROM admins WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return is_array($row) && $row['status'] === 'active' ? $row : null;
}

function vp3_require_customer(): array
{
    $customer = vp3_customer();
    if (!$customer) {
        vp3_flash('error', 'Please sign in to continue.');
        vp3_redirect('login.php');
    }
    return $customer;
}

function vp3_require_admin(array $roles = []): array
{
    $admin = vp3_admin();
    if (!$admin) {
        vp3_redirect('admin/login.php');
    }
    if ($roles && !in_array($admin['role'], $roles, true)) {
        http_response_code(403);
        exit('Access denied.');
    }
    return $admin;
}

function vp3_attempt_customer_login(string $email, string $password): bool
{
    if (!vp3_rate_limit('customer-login:' . strtolower($email), (int)vp3_config('security.login_attempt_limit', 5), 900)) {
        return false;
    }
    $stmt = vp3_db()->prepare('SELECT id, password_hash, status FROM customers WHERE email = ? LIMIT 1');
    $stmt->execute([strtolower($email)]);
    $row = $stmt->fetch();
    if (!is_array($row) || $row['status'] !== 'active' || !password_verify($password, (string)$row['password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['customer_id'] = (int)$row['id'];
    return true;
}

function vp3_attempt_admin_login(string $email, string $password): bool
{
    if (!vp3_rate_limit('admin-login:' . strtolower($email), (int)vp3_config('security.login_attempt_limit', 5), 900)) {
        return false;
    }
    $stmt = vp3_db()->prepare('SELECT id, password_hash, status FROM admins WHERE email = ? LIMIT 1');
    $stmt->execute([strtolower($email)]);
    $row = $stmt->fetch();
    if (!is_array($row) || $row['status'] !== 'active' || !password_verify($password, (string)$row['password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$row['id'];
    vp3_db()->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = ?')->execute([(int)$row['id']]);
    return true;
}

function vp3_logout(string $scope): void
{
    unset($_SESSION[$scope === 'admin' ? 'admin_id' : 'customer_id']);
    session_regenerate_id(true);
}

function vp3_admin_can(array $admin, string $permission): bool
{
    $role = (string)($admin['role'] ?? '');
    $permissions = [
        'owner' => ['*'],
        'super_admin' => ['*'],
        'operations' => [
            'dashboard.view','customers.view','customers.manage','products.view','products.manage',
            'orders.view','licenses.view','licenses.manage','hosting.view','hosting.manage',
            'installations.manage','releases.manage','support.manage','audit.view','network.view','network.manage','clips.moderate',
        ],
        'support' => [
            'dashboard.view','customers.view','orders.view','licenses.view','hosting.view','support.manage','network.view','clips.moderate',
        ],
        'billing' => [
            'dashboard.view','customers.view','orders.view','orders.manage','licenses.view','hosting.view',
        ],
    ];
    $allowed = $permissions[$role] ?? [];
    return in_array('*', $allowed, true) || in_array($permission, $allowed, true);
}

function vp3_require_admin_permission(string $permission): array
{
    $admin = vp3_require_admin();
    if (!vp3_admin_can($admin, $permission)) {
        http_response_code(403);
        exit('Access denied.');
    }
    return $admin;
}
