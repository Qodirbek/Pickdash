-- PickDash: multi-account faucet monitor
CREATE TABLE IF NOT EXISTS accounts (
    id              SERIAL PRIMARY KEY,
    site            VARCHAR(32)  NOT NULL DEFAULT 'tonpick',
    username        VARCHAR(128) NOT NULL DEFAULT '',
    label           VARCHAR(128) DEFAULT '',
    balance         VARCHAR(64)  DEFAULT '0',
    last_captcha    VARCHAR(64)  DEFAULT '',
    last_claim_at   TIMESTAMP NULL,
    next_claim_at   TIMESTAMP NULL,
    claims_today    INT DEFAULT 0,
    fails_today     INT DEFAULT 0,
    total_claims    INT DEFAULT 0,
    status          VARCHAR(32)  DEFAULT 'idle',
    last_message    TEXT DEFAULT '',
    api_balance     VARCHAR(32)  DEFAULT '',
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(site, username)
);

CREATE TABLE IF NOT EXISTS events (
    id          SERIAL PRIMARY KEY,
    account_id  INT REFERENCES accounts(id) ON DELETE CASCADE,
    site        VARCHAR(32) DEFAULT '',
    username    VARCHAR(128) DEFAULT '',
    event_type  VARCHAR(32) NOT NULL,
    captcha     VARCHAR(64) DEFAULT '',
    message     TEXT DEFAULT '',
    balance     VARCHAR(64) DEFAULT '',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_events_created ON events(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_accounts_site ON accounts(site);
