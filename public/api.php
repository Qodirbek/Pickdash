<?php
declare(strict_types=1);

/** Bot -> PickDash API. POST /api.php?key=... or X-API-Key header. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

ini_set('display_errors', '0');
ini_set('log_errors', '1');

$databaseFile = is_file(__DIR__ . '/lib/Database.php')
    ? __DIR__ . '/lib/Database.php'
    : __DIR__ . '/../lib/Database.php';
require_once $databaseFile;

$apiKey = trim((string)getenv('DASH_API_KEY'));
if ($apiKey === '') {
    error_log('[PickDash api.php] DASH_API_KEY is not configured.');
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'API key is not configured.']);
    exit;
}

$providedKey = (string)($_SERVER['HTTP_X_API_KEY'] ?? $_GET['key'] ?? '');
if ($providedKey === '' || !hash_equals($apiKey, $providedKey)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST, OPTIONS');
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

try {
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $action = strtolower(trim((string)($data['action'] ?? 'status')));
    $site = strtolower(trim((string)($data['site'] ?? 'tonpick')));
    $username = trim((string)($data['username'] ?? ''));

    if ($site === '' || !preg_match('/^[a-z0-9_-]{1,32}$/', $site)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid site']);
        exit;
    }
    if ($username === '' || strlen($username) > 128) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'username required (max 128 characters)']);
        exit;
    }

    $pdo = Database::pdo();
    Database::ensureSchema();

    $select = $pdo->prepare('SELECT * FROM accounts WHERE site = ? AND username = ? ORDER BY id LIMIT 1');
    $select->execute([$site, $username]);
    $previous = $select->fetch() ?: [];

    if (!in_array($action, ['claim', 'fail', 'status', 'heartbeat'], true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'unknown action']);
        exit;
    }

    $now = date('Y-m-d H:i:s');
    $nextAt = $previous['next_claim_at'] ?? null;
    if ($action === 'claim') {
        $nextSeconds = max(60, min(604800, (int)($data['next_claim_seconds'] ?? 3600)));
        $nextAt = date('Y-m-d H:i:s', time() + $nextSeconds);
    } elseif (isset($data['next_claim_seconds'])) {
        $nextAt = date('Y-m-d H:i:s', time() + max(0, min(604800, (int)$data['next_claim_seconds'])));
    } elseif (isset($data['next_claim_at'])) {
        $nextAt = (string)$data['next_claim_at'];
    }

    $claims = (int)($data['claims_today'] ?? ((int)($previous['claims_today'] ?? 0) + ($action === 'claim' ? 1 : 0)));
    $fails = (int)($data['fails_today'] ?? ((int)($previous['fails_today'] ?? 0) + ($action === 'fail' ? 1 : 0)));
    $total = (int)($data['total_claims'] ?? ((int)($previous['total_claims'] ?? 0) + ($action === 'claim' ? 1 : 0)));

    $status = (string)($data['status'] ?? (
        $action === 'claim' ? 'waiting' : ($action === 'fail' ? 'error' : 'running')
    ));
    $accountId = Database::upsertAccount([
        'site' => $site,
        'username' => $username,
        'label' => $data['label'] ?? ($previous['label'] ?? ''),
        'balance' => $data['balance'] ?? ($previous['balance'] ?? '0'),
        'last_captcha' => $data['captcha'] ?? ($previous['last_captcha'] ?? ''),
        'last_claim_at' => $action === 'claim' ? $now : ($previous['last_claim_at'] ?? null),
        'next_claim_at' => $nextAt,
        'claims_today' => max(0, $claims),
        'fails_today' => max(0, $fails),
        'total_claims' => max(0, $total),
        'status' => $status,
        'last_message' => $data['message'] ?? ($previous['last_message'] ?? ''),
        'api_balance' => $data['api_balance'] ?? ($previous['api_balance'] ?? ''),
    ]);

    if (in_array($action, ['claim', 'fail'], true)) {
        Database::addEvent($accountId, [
            'site' => $site,
            'username' => $username,
            'event_type' => $action,
            'captcha' => $data['captcha'] ?? '',
            'message' => $data['message'] ?? ($action === 'claim' ? 'Claim OK' : 'Claim failed'),
            'balance' => $data['balance'] ?? '',
        ]);
    }

    echo json_encode([
        'ok' => true,
        'id' => $accountId,
        'action' => $action,
        'next_claim_at' => $nextAt,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[PickDash api.php] ' . get_class($e) . ': ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Server yoki ma’lumotlar bazasi xatosi. Render Logs bo‘limini tekshiring.',
    ], JSON_UNESCAPED_UNICODE);
}
