CREATE TABLE IF NOT EXISTS domains (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    domain_uid VARCHAR(32) NOT NULL UNIQUE,
    site_id BIGINT UNSIGNED NOT NULL,
    domain VARCHAR(253) NOT NULL UNIQUE,
    type VARCHAR(32) NOT NULL DEFAULT 'primary',
    ssl_status VARCHAR(32) NOT NULL DEFAULT 'pending',
    dns_status VARCHAR(32) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL,
    INDEX idx_domains_site_id (site_id)
);
