CREATE TABLE IF NOT EXISTS pastes (
    id VARCHAR(32) PRIMARY KEY,
    data TEXT NOT NULL,
    created_at BIGINT NOT NULL,
    expires_at BIGINT NOT NULL,
    burn_after_read TINYINT(1) DEFAULT 0,
    has_password TINYINT(1) DEFAULT 0,
    views INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_expires ON pastes(expires_at);
