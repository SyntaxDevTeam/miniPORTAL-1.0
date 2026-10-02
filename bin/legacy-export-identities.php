<?php

declare(strict_types=1);

// Execute via SSH on the old server. Emits only the selected identity dataset to stdout.
$root = getenv('LEGACY_MINIPORTAL_ROOT') ?: '/var/www/syntaxdevteam.pl';
require $root . '/core/Autoloader.php';
\SyntaxDevTeam\Cms\Core\Autoloader::register();
$config = require $root . '/config/config.php';
$settings = $config['database'] ?? null;
if (!is_array($settings) || empty($settings['enabled'])
    || !in_array(strtolower((string) ($settings['database_type'] ?? '')), ['mysql', 'mariadb'], true)) {
    fwrite(STDERR, "Legacy MySQL database is not configured.\n");
    exit(2);
}
$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $settings['server'],
    (int) $settings['port'],
    $settings['database_name'],
    $settings['charset'] ?? 'utf8mb4',
);
$pdo = new PDO($dsn, $settings['username'], $settings['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->beginTransaction();
try {
    $snapshot = [
        'schema' => 1,
        'timezone' => $config['app']['timezone'] ?? 'Europe/Warsaw',
        'users' => $pdo->query('SELECT id, display_name, email, avatar_url, status, created_at, last_login_at FROM users ORDER BY id')->fetchAll(),
        'identities' => $pdo->query('SELECT user_id, provider, provider_subject, provider_login, provider_email, email_verified, linked_at, last_used_at FROM user_identities ORDER BY id')->fetchAll(),
        'roles' => $pdo->query('SELECT name, label FROM roles ORDER BY name')->fetchAll(),
        'permissions' => $pdo->query('SELECT name, label FROM permissions ORDER BY name')->fetchAll(),
        'user_roles' => $pdo->query('SELECT ur.user_id, r.name AS role_name FROM user_roles ur JOIN roles r ON r.id = ur.role_id ORDER BY ur.user_id, r.name')->fetchAll(),
        'role_permissions' => $pdo->query('SELECT r.name AS role_name, p.name AS permission_name FROM role_permissions rp JOIN roles r ON r.id = rp.role_id JOIN permissions p ON p.id = rp.permission_id ORDER BY r.name, p.name')->fetchAll(),
    ];
    $pdo->commit();
    echo json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Legacy identity export failed.\n");
    exit(2);
}
