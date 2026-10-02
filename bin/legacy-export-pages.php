<?php

declare(strict_types=1);

// Execute via SSH on the old VPS. Emits selected page data only; never writes to source.
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
$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $settings['server'], (int) $settings['port'], $settings['database_name'], $settings['charset'] ?? 'utf8mb4');
$pdo = new PDO($dsn, $settings['username'], $settings['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->beginTransaction();
try {
    $snapshot = [
        'schema' => 1,
        'timezone' => $config['app']['timezone'] ?? 'Europe/Warsaw',
        'pages' => $pdo->query('SELECT id, title, slug, summary, content, content_format, '
            . 'status, author_id, created_at, updated_at FROM core_pages ORDER BY id')->fetchAll(),
    ];
    $pdo->commit();
    echo json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Legacy pages export failed.\n");
    exit(2);
}
