-- PickDash PostgreSQL baseline schema.
-- Runtime migrations in lib/Database.php add missing columns safely.
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
);

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
);

CREATE INDEX IF NOT EXISTS idx_events_created ON events(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_accounts_site ON accounts(site);
CREATE UNIQUE INDEX IF NOT EXISTS idx_accounts_site_username ON accounts(site, username);
