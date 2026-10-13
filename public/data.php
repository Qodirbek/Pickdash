<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

ini_set('display_errors', '0');
ini_set('log_errors', '1');

$databaseFile = is_file(__DIR__ . '/lib/Database.php')
    ? __DIR__ . '/lib/Database.php'       // Docker: public files are copied to /var/www/html
    : __DIR__ . '/../lib/Database.php';    // local PHP server: public/ is document root
require_once $databaseFile;

try {
    $pdo = Database::pdo();
    Database::ensureSchema();

    $accounts = $pdo->query('SELECT * FROM accounts ORDER BY site, username')->fetchAll();
    $events = $pdo->query('SELECT * FROM events ORDER BY id DESC LIMIT 80')->fetchAll();

    $summary = [];
    foreach ($accounts as $account) {
        $site = (string)($account['site'] ?? 'unknown');
        if (!isset($summary[$site])) {
            $summary[$site] = [
                'accounts' => 0,
                'claims_today' => 0,
                'fails_today' => 0,
                'balance_sum' => 0.0,
            ];
        }
        $summary[$site]['accounts']++;
        $summary[$site]['claims_today'] += (int)($account['claims_today'] ?? 0);
        $summary[$site]['fails_today'] += (int)($account['fails_today'] ?? 0);
        $summary[$site]['balance_sum'] += (float)($account['balance'] ?? 0);
    }

    echo json_encode([
        'ok' => true,
        'server_time' => date('c'),
        'database' => Database::isPostgres() ? 'postgresql' : 'sqlite',
        'summary' => $summary,
        'accounts' => $accounts,
        'events' => $events,
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
} catch (Throwable $e) {
    error_log('[PickDash data.php] ' . get_class($e) . ': ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Server yoki ma’lumotlar bazasi xatosi. Render Logs bo‘limini tekshiring.',
    ], JSON_UNESCAPED_UNICODE);
}
