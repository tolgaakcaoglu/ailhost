CREATE TABLE IF NOT EXISTS job_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_uid VARCHAR(32) NOT NULL,
    stream VARCHAR(16) NOT NULL DEFAULT 'info',
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_job_logs_job_uid (job_uid),
    INDEX idx_job_logs_created_at (created_at)
);
