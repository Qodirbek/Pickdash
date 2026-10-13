<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../lib/Database.php';

try {
    Database::ensureSchema();
    $pdo = Database::pdo();

    $accounts = $pdo->query('SELECT * FROM accounts ORDER BY site, username')->fetchAll();
    $events = $pdo->query('SELECT * FROM events ORDER BY id DESC LIMIT 80')->fetchAll();

    $summary = [];
    foreach ($accounts as $a) {
        $site = $a['site'];
        if (!isset($summary[$site])) {
            $summary[$site] = ['accounts' => 0, 'claims_today' => 0, 'fails_today' => 0, 'balance_sum' => 0.0];
        }
        $summary[$site]['accounts']++;
        $summary[$site]['claims_today'] += (int)$a['claims_today'];
        $summary[$site]['fails_today'] += (int)$a['fails_today'];
        $summary[$site]['balance_sum'] += (float)$a['balance'];
    }

    echo json_encode([
        'ok' => true,
        'server_time' => date('c'),
        'summary' => $summary,
        'accounts' => $accounts,
        'events' => $events,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
