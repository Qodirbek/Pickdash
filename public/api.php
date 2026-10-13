<?php
/**
 * Bot → dashboard API
 * POST /api.php?key=DASH_API_KEY
 * JSON body: {action: claim|fail|status|heartbeat, site, username, ...}
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../lib/Database.php';

$apiKey = getenv('DASH_API_KEY') ?: 'pickdash-secret-change-me';
$got = $_GET['key'] ?? ($_SERVER['HTTP_X_API_KEY'] ?? '');
if (!hash_equals($apiKey, (string)$got)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) $data = $_POST;

$action = $data['action'] ?? 'status';
$site = strtolower(trim($data['site'] ?? 'tonpick'));
$user = trim($data['username'] ?? '');

if ($user === '') {
    echo json_encode(['ok' => false, 'error' => 'username required']);
    exit;
}

try {
    Database::ensureSchema();

    if ($action === 'claim') {
        // bot stats ni o'zi yuboradi yoki +1
        $st = Database::pdo()->prepare('SELECT * FROM accounts WHERE site=? AND username=?');
        $st->execute([$site, $user]);
        $prev = $st->fetch() ?: [];

        $claimsToday = isset($data['claims_today']) ? (int)$data['claims_today'] : ((int)($prev['claims_today'] ?? 0) + 1);
        $total = isset($data['total_claims']) ? (int)$data['total_claims'] : ((int)($prev['total_claims'] ?? 0) + 1);
        $nextSec = (int)($data['next_claim_seconds'] ?? 3600);
        $nextAt = date('Y-m-d H:i:s', time() + max(60, $nextSec));

        $id = Database::upsertAccount([
            'site' => $site,
            'username' => $user,
            'label' => $data['label'] ?? null,
            'balance' => $data['balance'] ?? null,
            'last_captcha' => $data['captcha'] ?? null,
            'last_claim_at' => date('Y-m-d H:i:s'),
            'next_claim_at' => $nextAt,
            'claims_today' => $claimsToday,
            'fails_today' => isset($data['fails_today']) ? (int)$data['fails_today'] : ($prev['fails_today'] ?? 0),
            'total_claims' => $total,
            'status' => 'waiting',
            'last_message' => $data['message'] ?? 'Claim OK',
            'api_balance' => $data['api_balance'] ?? null,
        ]);
        Database::addEvent($id, [
            'site' => $site, 'username' => $user,
            'event_type' => 'claim',
            'captcha' => $data['captcha'] ?? '',
            'message' => $data['message'] ?? 'Claim OK',
            'balance' => $data['balance'] ?? '',
        ]);
        echo json_encode(['ok' => true, 'id' => $id, 'next_claim_at' => $nextAt]);
        exit;
    }

    if ($action === 'fail') {
        $st = Database::pdo()->prepare('SELECT * FROM accounts WHERE site=? AND username=?');
        $st->execute([$site, $user]);
        $prev = $st->fetch() ?: [];
        $fails = isset($data['fails_today']) ? (int)$data['fails_today'] : ((int)($prev['fails_today'] ?? 0) + 1);
        $id = Database::upsertAccount([
            'site' => $site,
            'username' => $user,
            'balance' => $data['balance'] ?? null,
            'fails_today' => $fails,
            'claims_today' => $prev['claims_today'] ?? 0,
            'total_claims' => $prev['total_claims'] ?? 0,
            'status' => 'error',
            'last_message' => $data['message'] ?? 'fail',
            'last_captcha' => $data['captcha'] ?? null,
        ]);
        Database::addEvent($id, [
            'site' => $site, 'username' => $user,
            'event_type' => 'fail',
            'captcha' => $data['captcha'] ?? '',
            'message' => $data['message'] ?? 'fail',
            'balance' => $data['balance'] ?? '',
        ]);
        echo json_encode(['ok' => true, 'id' => $id]);
        exit;
    }

    if ($action === 'status' || $action === 'heartbeat') {
        $nextAt = null;
        if (!empty($data['next_claim_seconds'])) {
            $nextAt = date('Y-m-d H:i:s', time() + (int)$data['next_claim_seconds']);
        } elseif (!empty($data['next_claim_at'])) {
            $nextAt = $data['next_claim_at'];
        }
        $st = Database::pdo()->prepare('SELECT * FROM accounts WHERE site=? AND username=?');
        $st->execute([$site, $user]);
        $prev = $st->fetch() ?: [];
        $id = Database::upsertAccount([
            'site' => $site,
            'username' => $user,
            'label' => $data['label'] ?? null,
            'balance' => $data['balance'] ?? null,
            'next_claim_at' => $nextAt,
            'claims_today' => $data['claims_today'] ?? ($prev['claims_today'] ?? 0),
            'fails_today' => $data['fails_today'] ?? ($prev['fails_today'] ?? 0),
            'total_claims' => $data['total_claims'] ?? ($prev['total_claims'] ?? 0),
            'status' => $data['status'] ?? 'running',
            'last_message' => $data['message'] ?? null,
            'api_balance' => $data['api_balance'] ?? null,
            'last_captcha' => $data['captcha'] ?? null,
        ]);
        echo json_encode(['ok' => true, 'id' => $id]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'unknown action']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
