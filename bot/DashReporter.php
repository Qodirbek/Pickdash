<?php
/**
 * Tonpick/Tronpick botga qo'shish:
 *   require_once __DIR__ . '/DashReporter.php';
 *   DashReporter::claim([...]);
 *
 * ENV:
 *   DASH_URL=https://your-dash.onrender.com/api.php
 *   DASH_API_KEY=pickdash-secret-change-me
 */
class DashReporter {
    public static function send(array $payload): bool {
        $url = getenv('DASH_URL') ?: '';
        $key = getenv('DASH_API_KEY') ?: 'pickdash-secret-change-me';
        if ($url === '') return false;

        $sep = (strpos($url, '?') !== false) ? '&' : '?';
        $full = $url . $sep . 'key=' . urlencode($key);

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nX-Api-Key: {$key}\r\n",
                'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'timeout' => 8,
                'ignore_errors' => true,
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $res = @file_get_contents($full, false, $ctx);
        return $res !== false;
    }

    public static function claim(string $site, string $username, string $balance, string $captcha, string $message, int $nextSeconds = 3600, array $extra = []): void {
        self::send(array_merge([
            'action' => 'claim',
            'site' => $site,
            'username' => $username,
            'balance' => $balance,
            'captcha' => $captcha,
            'message' => $message,
            'next_claim_seconds' => $nextSeconds,
        ], $extra));
    }

    public static function fail(string $site, string $username, string $message, string $captcha = '', array $extra = []): void {
        self::send(array_merge([
            'action' => 'fail',
            'site' => $site,
            'username' => $username,
            'message' => $message,
            'captcha' => $captcha,
        ], $extra));
    }

    public static function heartbeat(string $site, string $username, array $extra = []): void {
        self::send(array_merge([
            'action' => 'heartbeat',
            'site' => $site,
            'username' => $username,
            'status' => 'running',
        ], $extra));
    }
}
