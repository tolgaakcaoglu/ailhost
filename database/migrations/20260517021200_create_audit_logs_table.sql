CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    audit_uid VARCHAR(32) NOT NULL UNIQUE,
    actor_email VARCHAR(190) NULL,
    action VARCHAR(120) NOT NULL,
    resource_type VARCHAR(120) NOT NULL,
    resource_id VARCHAR(190) NOT NULL,
    metadata JSON NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_audit_action (action),
    INDEX idx_audit_created_at (created_at)
);
