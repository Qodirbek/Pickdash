<?php
declare(strict_types=1);

/**
 * PickDash database layer.
 * Uses Render PostgreSQL when DATABASE_URL/DB_URL is configured;
 * otherwise uses SQLite (local development).
 */
final class Database
{
    private static ?PDO $pdo = null;
    private static bool $schemaReady = false;
    private static bool $schemaRunning = false;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            if (!self::$schemaReady && !self::$schemaRunning) {
                self::ensureSchema();
            }
            return self::$pdo;
        }

        $url = trim((string)(getenv('DATABASE_URL') ?: getenv('DB_URL') ?: ''));

        if ($url !== '' && preg_match('/^postgres(?:ql)?:\/\//i', $url)) {
            self::$pdo = self::connectPostgres($url);
        } else {
            $path = trim((string)(getenv('SQLITE_PATH') ?: dirname(__DIR__) . '/data/pickdash.sqlite'));
            $dir = dirname($path);
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new RuntimeException('SQLite data papkasini yaratib bo‘lmadi.');
            }
            self::$pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            self::$pdo->exec('PRAGMA foreign_keys = ON');
            self::$pdo->exec('PRAGMA busy_timeout = 5000');
        }

        if (!self::$schemaRunning && !self::$schemaReady) {
            self::ensureSchema();
        }

        return self::$pdo;
    }

    private static function connectPostgres(string $url): PDO
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host']) || empty($parts['path'])) {
            throw new RuntimeException('DATABASE_URL formati noto‘g‘ri.');
        }

        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $dsn = 'pgsql:host=' . $parts['host']
            . ';port=' . (int)($parts['port'] ?? 5432)
            . ';dbname=' . ltrim((string)$parts['path'], '/');

        if (!empty($query['sslmode'])) {
            $dsn .= ';sslmode=' . preg_replace('/[^a-z]/i', '', (string)$query['sslmode']);
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        return new PDO(
            $dsn,
            rawurldecode((string)($parts['user'] ?? '')),
            rawurldecode((string)($parts['pass'] ?? '')),
            $options
        );
    }

    public static function isPostgres(): bool
    {
        return self::pdo()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
    }

    /**
     * Creates missing tables and adds missing columns without dropping data.
     * Only additive schema changes are automated here.
     */
    public static function ensureSchema(): void
    {
        if (self::$schemaReady || self::$schemaRunning) {
            return;
        }

        self::$schemaRunning = true;

        try {
            // pdo() returns the connection without recursively migrating while this flag is set.
            $pdo = self::$pdo instanceof PDO ? self::$pdo : self::connectionOnly();
            $pg = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';

            if ($pg) {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS accounts (
                        id BIGSERIAL PRIMARY KEY,
                        site VARCHAR(32) NOT NULL DEFAULT 'tonpick',
                        username VARCHAR(128) NOT NULL DEFAULT '',
                        label VARCHAR(128) DEFAULT '',
                        balance VARCHAR(64) DEFAULT '0',
                        last_captcha VARCHAR(64) DEFAULT '',
                        last_claim_at TIMESTAMP NULL,
                        next_claim_at TIMESTAMP NULL,
                        claims_today INTEGER DEFAULT 0,
                        fails_today INTEGER DEFAULT 0,
                        total_claims INTEGER DEFAULT 0,
                        status VARCHAR(32) DEFAULT 'idle',
                        last_message TEXT DEFAULT '',
                        api_balance VARCHAR(32) DEFAULT '',
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ");
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS events (
                        id BIGSERIAL PRIMARY KEY,
                        account_id BIGINT NULL,
                        site VARCHAR(32) DEFAULT '',
                        username VARCHAR(128) DEFAULT '',
                        event_type VARCHAR(32) NOT NULL DEFAULT 'info',
                        captcha VARCHAR(64) DEFAULT '',
                        message TEXT DEFAULT '',
                        balance VARCHAR(64) DEFAULT '',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ");
                // Add a missing FK only for newly created tables; do not risk breaking legacy data.
                $pdo->exec('CREATE INDEX IF NOT EXISTS idx_events_created ON events(created_at DESC)');
                $pdo->exec('CREATE INDEX IF NOT EXISTS idx_accounts_site ON accounts(site)');
                try {
                    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_accounts_site_username ON accounts(site, username)');
                } catch (Throwable $e) {
                    // Legacy databases may already contain duplicate rows; don't block startup.
                    error_log('[PickDash migration] Unique index not created; duplicate site/username rows may exist: ' . $e->getMessage());
                }
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
                        created_at TEXT DEFAULT CURRENT_TIMESTAMP
                    )
                ");
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS events (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        account_id INTEGER NULL,
                        site TEXT DEFAULT '',
                        username TEXT DEFAULT '',
                        event_type TEXT NOT NULL DEFAULT 'info',
                        captcha TEXT DEFAULT '',
                        message TEXT DEFAULT '',
                        balance TEXT DEFAULT '',
                        created_at TEXT DEFAULT CURRENT_TIMESTAMP
                    )
                ");
                $pdo->exec('CREATE INDEX IF NOT EXISTS idx_events_created ON events(created_at DESC)');
                $pdo->exec('CREATE INDEX IF NOT EXISTS idx_accounts_site ON accounts(site)');
                // SQLite allows this index when no duplicate legacy rows exist.
                try {
                    try {
                    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_accounts_site_username ON accounts(site, username)');
                } catch (Throwable $e) {
                    // Legacy databases may already contain duplicate rows; don't block startup.
                    error_log('[PickDash migration] Unique index not created; duplicate site/username rows may exist: ' . $e->getMessage());
                }
                } catch (Throwable $e) {
                    error_log('[PickDash migration] Unique index not created; duplicate site/username rows may exist: ' . $e->getMessage());
                }
            }

            $accountColumns = $pg ? [
                'site' => "VARCHAR(32) NOT NULL DEFAULT 'tonpick'",
                'username' => "VARCHAR(128) NOT NULL DEFAULT ''",
                'label' => "VARCHAR(128) DEFAULT ''",
                'balance' => "VARCHAR(64) DEFAULT '0'",
                'last_captcha' => "VARCHAR(64) DEFAULT ''",
                'last_claim_at' => 'TIMESTAMP NULL',
                'next_claim_at' => 'TIMESTAMP NULL',
                'claims_today' => 'INTEGER DEFAULT 0',
                'fails_today' => 'INTEGER DEFAULT 0',
                'total_claims' => 'INTEGER DEFAULT 0',
                'status' => "VARCHAR(32) DEFAULT 'idle'",
                'last_message' => "TEXT DEFAULT ''",
                'api_balance' => "VARCHAR(32) DEFAULT ''",
                'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
                'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            ] : [
                'site' => "TEXT NOT NULL DEFAULT 'tonpick'",
                'username' => "TEXT NOT NULL DEFAULT ''",
                'label' => "TEXT DEFAULT ''",
                'balance' => "TEXT DEFAULT '0'",
                'last_captcha' => "TEXT DEFAULT ''",
                'last_claim_at' => 'TEXT NULL',
                'next_claim_at' => 'TEXT NULL',
                'claims_today' => 'INTEGER DEFAULT 0',
                'fails_today' => 'INTEGER DEFAULT 0',
                'total_claims' => 'INTEGER DEFAULT 0',
                'status' => "TEXT DEFAULT 'idle'",
                'last_message' => "TEXT DEFAULT ''",
                'api_balance' => "TEXT DEFAULT ''",
                'updated_at' => 'TEXT DEFAULT CURRENT_TIMESTAMP',
                'created_at' => 'TEXT DEFAULT CURRENT_TIMESTAMP',
            ];

            $eventColumns = $pg ? [
                'account_id' => 'BIGINT NULL',
                'site' => "VARCHAR(32) DEFAULT ''",
                'username' => "VARCHAR(128) DEFAULT ''",
                'event_type' => "VARCHAR(32) NOT NULL DEFAULT 'info'",
                'captcha' => "VARCHAR(64) DEFAULT ''",
                'message' => "TEXT DEFAULT ''",
                'balance' => "VARCHAR(64) DEFAULT ''",
                'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            ] : [
                'account_id' => 'INTEGER NULL',
                'site' => "TEXT DEFAULT ''",
                'username' => "TEXT DEFAULT ''",
                'event_type' => "TEXT NOT NULL DEFAULT 'info'",
                'captcha' => "TEXT DEFAULT ''",
                'message' => "TEXT DEFAULT ''",
                'balance' => "TEXT DEFAULT ''",
                'created_at' => 'TEXT DEFAULT CURRENT_TIMESTAMP',
            ];

            foreach ($accountColumns as $column => $definition) {
                self::ensureColumnOn($pdo, 'accounts', $column, $definition, $pg);
            }
            foreach ($eventColumns as $column => $definition) {
                self::ensureColumnOn($pdo, 'events', $column, $definition, $pg);
            }

            self::$schemaReady = true;
        } catch (Throwable $e) {
            error_log('[PickDash schema] ' . get_class($e) . ': ' . $e->getMessage());
            throw $e;
        } finally {
            self::$schemaRunning = false;
        }
    }

    private static function connectionOnly(): PDO
    {
        // pdo() handles connection setup and will skip ensureSchema while schemaRunning=true.
        return self::pdo();
    }

    private static function ensureColumnOn(PDO $pdo, string $table, string $column, string $definition, bool $pg): void
    {
        // Table/column names are constants from this class, not user input.
        if ($pg) {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS {$column} {$definition}");
            return;
        }

        $columns = $pdo->query("PRAGMA table_info({$table})")->fetchAll();
        foreach ($columns as $existing) {
            if (strcasecmp((string)$existing['name'], $column) === 0) {
                return;
            }
        }
        $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    }

    public static function upsertAccount(array $data): int
    {
        $pdo = self::pdo();
        $site = strtolower(trim((string)($data['site'] ?? 'tonpick')));
        $username = trim((string)($data['username'] ?? ''));

        if ($site === '' || $username === '') {
            throw new InvalidArgumentException('site va username bo‘sh bo‘lmasligi kerak.');
        }

        $statement = $pdo->prepare('SELECT * FROM accounts WHERE site = ? AND username = ? ORDER BY id LIMIT 1');
        $statement->execute([$site, $username]);
        $existing = $statement->fetch() ?: null;
        $now = date('Y-m-d H:i:s');

        $fields = [
            'label' => $data['label'] ?? ($existing['label'] ?? ''),
            'balance' => $data['balance'] ?? ($existing['balance'] ?? '0'),
            'last_captcha' => $data['last_captcha'] ?? ($existing['last_captcha'] ?? ''),
            'last_claim_at' => $data['last_claim_at'] ?? ($existing['last_claim_at'] ?? null),
            'next_claim_at' => $data['next_claim_at'] ?? ($existing['next_claim_at'] ?? null),
            'claims_today' => (int)($data['claims_today'] ?? ($existing['claims_today'] ?? 0)),
            'fails_today' => (int)($data['fails_today'] ?? ($existing['fails_today'] ?? 0)),
            'total_claims' => (int)($data['total_claims'] ?? ($existing['total_claims'] ?? 0)),
            'status' => $data['status'] ?? ($existing['status'] ?? 'idle'),
            'last_message' => $data['last_message'] ?? ($existing['last_message'] ?? ''),
            'api_balance' => $data['api_balance'] ?? ($existing['api_balance'] ?? ''),
            'updated_at' => $now,
        ];

        if ($existing) {
            $assignments = [];
            $values = [];
            foreach ($fields as $column => $value) {
                $assignments[] = $column . ' = ?';
                $values[] = $value;
            }
            $values[] = (int)$existing['id'];
            $update = $pdo->prepare('UPDATE accounts SET ' . implode(', ', $assignments) . ' WHERE id = ?');
            $update->execute($values);
            return (int)$existing['id'];
        }

        $insertData = array_merge([
            'site' => $site,
            'username' => $username,
            'created_at' => $now,
        ], $fields);
        $columns = array_keys($insertData);
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $insert = $pdo->prepare('INSERT INTO accounts (' . implode(',', $columns) . ') VALUES (' . $placeholders . ')');
        $insert->execute(array_values($insertData));

        $id = (int)$pdo->lastInsertId();
        if ($id <= 0 && self::isPostgres()) {
            $lookup = $pdo->prepare('SELECT id FROM accounts WHERE site = ? AND username = ?');
            $lookup->execute([$site, $username]);
            $id = (int)$lookup->fetchColumn();
        }
        return $id;
    }

    public static function addEvent(int $accountId, array $event): void
    {
        $pdo = self::pdo();
        $statement = $pdo->prepare(
            'INSERT INTO events (account_id, site, username, event_type, captcha, message, balance, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $accountId > 0 ? $accountId : null,
            (string)($event['site'] ?? ''),
            (string)($event['username'] ?? ''),
            (string)($event['event_type'] ?? 'info'),
            (string)($event['captcha'] ?? ''),
            (string)($event['message'] ?? ''),
            (string)($event['balance'] ?? ''),
            date('Y-m-d H:i:s'),
        ]);
    }
}
