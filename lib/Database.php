<?php
/**
 * PickDash DB — SQLite (local/docker) yoki PostgreSQL (Render DATABASE_URL)
 * Saqlashdan oldin ustun/jadval yo'q bo'lsa yaratadi.
 */
class Database {
    private static $pdo = null;

    public static function pdo(): PDO {
        if (self::$pdo) return self::$pdo;

        $url = getenv('DATABASE_URL') ?: (getenv('DB_URL') ?: '');
        if ($url && strpos($url, 'postgres') !== false) {
            // Render: postgres://user:pass@host:5432/db
            $url = preg_replace('#^postgres(ql)?://#', 'postgresql://', $url);
            $parts = parse_url($url);
            $dsn = sprintf(
                'pgsql:host=%s;port=%d;dbname=%s',
                $parts['host'] ?? 'localhost',
                $parts['port'] ?? 5432,
                ltrim($parts['path'] ?? '/pickdash', '/')
            );
            self::$pdo = new PDO($dsn, $parts['user'] ?? '', $parts['pass'] ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } else {
            $path = getenv('SQLITE_PATH') ?: (__DIR__ . '/../data/pickdash.sqlite');
            $dir = dirname($path);
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            self::$pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            self::$pdo->exec('PRAGMA foreign_keys = ON');
        }

        self::ensureSchema();
        return self::$pdo;
    }

    public static function isPostgres(): bool {
        return self::pdo()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
    }

    /** Ustun yo'q bo'lsa qo'shadi (SQLite + Postgres) */
    public static function ensureColumn(string $table, string $column, string $definition): void {
        $pdo = self::pdo();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'pgsql') {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS {$column} {$definition}");
            return;
        }

        // SQLite: PRAGMA table_info
        $cols = $pdo->query("PRAGMA table_info({$table})")->fetchAll();
        foreach ($cols as $c) {
            if (strcasecmp($c['name'], $column) === 0) return;
        }
        $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    }

    public static function ensureSchema(): void {
        $pdo = self::pdo();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'pgsql') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS accounts (
                    id SERIAL PRIMARY KEY,
                    site VARCHAR(32) NOT NULL DEFAULT 'tonpick',
                    username VARCHAR(128) NOT NULL DEFAULT '',
                    label VARCHAR(128) DEFAULT '',
                    balance VARCHAR(64) DEFAULT '0',
                    last_captcha VARCHAR(64) DEFAULT '',
                    last_claim_at TIMESTAMP NULL,
                    next_claim_at TIMESTAMP NULL,
                    claims_today INT DEFAULT 0,
                    fails_today INT DEFAULT 0,
                    total_claims INT DEFAULT 0,
                    status VARCHAR(32) DEFAULT 'idle',
                    last_message TEXT DEFAULT '',
                    api_balance VARCHAR(32) DEFAULT '',
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(site, username)
                );
                CREATE TABLE IF NOT EXISTS events (
                    id SERIAL PRIMARY KEY,
                    account_id INT REFERENCES accounts(id) ON DELETE CASCADE,
                    site VARCHAR(32) DEFAULT '',
                    username VARCHAR(128) DEFAULT '',
                    event_type VARCHAR(32) NOT NULL,
                    captcha VARCHAR(64) DEFAULT '',
                    message TEXT DEFAULT '',
                    balance VARCHAR(64) DEFAULT '',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );
                CREATE INDEX IF NOT EXISTS idx_events_created ON events(created_at DESC);
                CREATE INDEX IF NOT EXISTS idx_accounts_site ON accounts(site);
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS accounts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    site TEXT NOT NULL DEFAULT 'tonpick',
                    username TEXT NOT NULL DEFAULT '',
                    label TEXT DEFAULT '',
                    balance TEXT DEFAULT '0',
                    last_captcha TEXT DEFAULT '',
                    last_claim_at TEXT NULL,
                    next_claim_at TEXT NULL,
                    claims_today INTEGER DEFAULT 0,
                    fails_today INTEGER DEFAULT 0,
                    total_claims INTEGER DEFAULT 0,
                    status TEXT DEFAULT 'idle',
                    last_message TEXT DEFAULT '',
                    api_balance TEXT DEFAULT '',
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(site, username)
                );
                CREATE TABLE IF NOT EXISTS events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    account_id INTEGER,
                    site TEXT DEFAULT '',
                    username TEXT DEFAULT '',
                    event_type TEXT NOT NULL,
                    captcha TEXT DEFAULT '',
                    message TEXT DEFAULT '',
                    balance TEXT DEFAULT '',
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP
                );
                CREATE INDEX IF NOT EXISTS idx_events_created ON events(created_at DESC);
                CREATE INDEX IF NOT EXISTS idx_accounts_site ON accounts(site);
            ");
        }

        // Kelajakdagi ustunlar — bor-yo'qligini tekshirib qo'shadi
        $accountCols = [
            'label'         => $driver === 'pgsql' ? 'VARCHAR(128) DEFAULT \'\'' : "TEXT DEFAULT ''",
            'balance'       => $driver === 'pgsql' ? 'VARCHAR(64) DEFAULT \'0\'' : "TEXT DEFAULT '0'",
            'last_captcha'  => $driver === 'pgsql' ? 'VARCHAR(64) DEFAULT \'\'' : "TEXT DEFAULT ''",
            'last_claim_at' => $driver === 'pgsql' ? 'TIMESTAMP NULL' : 'TEXT NULL',
            'next_claim_at' => $driver === 'pgsql' ? 'TIMESTAMP NULL' : 'TEXT NULL',
            'claims_today'  => $driver === 'pgsql' ? 'INT DEFAULT 0' : 'INTEGER DEFAULT 0',
            'fails_today'   => $driver === 'pgsql' ? 'INT DEFAULT 0' : 'INTEGER DEFAULT 0',
            'total_claims'  => $driver === 'pgsql' ? 'INT DEFAULT 0' : 'INTEGER DEFAULT 0',
            'status'        => $driver === 'pgsql' ? 'VARCHAR(32) DEFAULT \'idle\'' : "TEXT DEFAULT 'idle'",
            'last_message'  => $driver === 'pgsql' ? 'TEXT DEFAULT \'\'' : "TEXT DEFAULT ''",
            'api_balance'   => $driver === 'pgsql' ? 'VARCHAR(32) DEFAULT \'\'' : "TEXT DEFAULT ''",
            'updated_at'    => $driver === 'pgsql' ? 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP' : 'TEXT DEFAULT CURRENT_TIMESTAMP',
        ];
        foreach ($accountCols as $col => $def) {
            try { self::ensureColumn('accounts', $col, $def); } catch (Throwable $e) { /* ignore */ }
        }
    }

    public static function upsertAccount(array $d): int {
        $pdo = self::pdo();
        $site = $d['site'] ?? 'tonpick';
        $user = $d['username'] ?? '';

        $st = $pdo->prepare('SELECT id, claims_today, fails_today, total_claims FROM accounts WHERE site = ? AND username = ?');
        $st->execute([$site, $user]);
        $row = $st->fetch();

        $now = date('Y-m-d H:i:s');
        $claimsToday = (int)($d['claims_today'] ?? ($row['claims_today'] ?? 0));
        $failsToday  = (int)($d['fails_today'] ?? ($row['fails_today'] ?? 0));
        $total       = (int)($d['total_claims'] ?? ($row['total_claims'] ?? 0));

        if ($row) {
            $sql = "UPDATE accounts SET
                label = COALESCE(?, label),
                balance = COALESCE(?, balance),
                last_captcha = COALESCE(?, last_captcha),
                last_claim_at = COALESCE(?, last_claim_at),
                next_claim_at = COALESCE(?, next_claim_at),
                claims_today = ?,
                fails_today = ?,
                total_claims = ?,
                status = COALESCE(?, status),
                last_message = COALESCE(?, last_message),
                api_balance = COALESCE(?, api_balance),
                updated_at = ?
                WHERE id = ?";
            $pdo->prepare($sql)->execute([
                $d['label'] ?? null,
                $d['balance'] ?? null,
                $d['last_captcha'] ?? null,
                $d['last_claim_at'] ?? null,
                $d['next_claim_at'] ?? null,
                $claimsToday,
                $failsToday,
                $total,
                $d['status'] ?? null,
                $d['last_message'] ?? null,
                $d['api_balance'] ?? null,
                $now,
                $row['id'],
            ]);
            return (int)$row['id'];
        }

        $sql = "INSERT INTO accounts
            (site, username, label, balance, last_captcha, last_claim_at, next_claim_at,
             claims_today, fails_today, total_claims, status, last_message, api_balance, updated_at, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $pdo->prepare($sql)->execute([
            $site, $user,
            $d['label'] ?? '',
            $d['balance'] ?? '0',
            $d['last_captcha'] ?? '',
            $d['last_claim_at'] ?? null,
            $d['next_claim_at'] ?? null,
            $claimsToday, $failsToday, $total,
            $d['status'] ?? 'idle',
            $d['last_message'] ?? '',
            $d['api_balance'] ?? '',
            $now, $now,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function addEvent(int $accountId, array $e): void {
        $pdo = self::pdo();
        $pdo->prepare("INSERT INTO events (account_id, site, username, event_type, captcha, message, balance, created_at)
            VALUES (?,?,?,?,?,?,?,?)")->execute([
            $accountId,
            $e['site'] ?? '',
            $e['username'] ?? '',
            $e['event_type'] ?? 'info',
            $e['captcha'] ?? '',
            $e['message'] ?? '',
            $e['balance'] ?? '',
            date('Y-m-d H:i:s'),
        ]);
    }
}
