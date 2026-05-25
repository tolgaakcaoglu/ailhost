<?php

declare(strict_types=1);

namespace Ailhost\Adapters;

use Ailhost\Services\JsonStateStore;

final class PhpAdapter implements SystemAdapterInterface
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly JsonStateStore $jsonStateStore = new JsonStateStore()
    )
    {
    }

    public function execute(array $payload): array
    {
        $action = (string) ($payload['action'] ?? '');
        if (!in_array($action, ['apply_site_profile', 'service_health'], true)) {
            return ['ok' => false, 'message' => 'Desteklenmeyen PHP işlemi.'];
        }

        if ($action === 'service_health') {
            return $this->serviceHealth();
        }

        $siteId = (string) ($payload['site_id'] ?? '');
        if ($siteId === '') {
            return ['ok' => false, 'message' => 'Site bilgisi eksik.'];
        }

        $dir = $this->projectRoot . '/var/generated/php';
        if (!is_dir($dir) && !mkdir($concurrentDirectory = $dir, 0755, true) && !is_dir($concurrentDirectory)) {
            return ['ok' => false, 'message' => 'PHP çıktı dizini oluşturulamadı.'];
        }

        $poolName = 'site_' . preg_replace('/[^a-z0-9_]/', '_', strtolower($siteId));
        $documentRoot = (string) ($payload['document_root'] ?? '/var/www');
        $templateFile = $this->projectRoot . '/templates/php-fpm-site.pool.conf.template';
        if (!is_file($templateFile)) {
            return ['ok' => false, 'message' => 'PHP-FPM pool template bulunamadı.'];
        }
        $template = (string) file_get_contents($templateFile);
        $poolContent = str_replace(
            ['{{POOL_NAME}}', '{{PHP_VERSION}}', '{{MEMORY_LIMIT}}', '{{UPLOAD_MAX_FILESIZE}}', '{{MAX_EXECUTION_TIME}}', '{{DOCUMENT_ROOT}}'],
            [
                $poolName,
                str_replace('.', '', (string) ($payload['php_version'] ?? '8.3')),
                (string) ($payload['memory_limit'] ?? '256M'),
                (string) ($payload['upload_max_filesize'] ?? '64M'),
                (string) ((int) ($payload['max_execution_time'] ?? 120)),
                $documentRoot,
            ],
            $template
        );

        $poolDir = $dir . '/pools';
        if (!is_dir($poolDir) && !mkdir($concurrentDirectory = $poolDir, 0755, true) && !is_dir($concurrentDirectory)) {
            return ['ok' => false, 'message' => 'PHP pool dizini oluşturulamadı.'];
        }
        $poolFile = $poolDir . '/' . $poolName . '.conf';
        if (file_put_contents($poolFile, $poolContent) === false) {
            return ['ok' => false, 'message' => 'PHP-FPM pool dosyası yazılamadı.'];
        }

        $file = $dir . '/' . $siteId . '.json';
        $profile = [
            'site_id' => $siteId,
            'php_version' => (string) ($payload['php_version'] ?? '8.3'),
            'memory_limit' => (string) ($payload['memory_limit'] ?? '256M'),
            'upload_max_filesize' => (string) ($payload['upload_max_filesize'] ?? '64M'),
            'max_execution_time' => (int) ($payload['max_execution_time'] ?? 120),
            'pool_file' => $poolFile,
            'updated_at' => date(DATE_ATOM),
        ];

        if (!$this->jsonStateStore->writeArray($file, $profile)) {
            return ['ok' => false, 'message' => 'PHP profil kaydı yazılamadı.'];
        }

        return ['ok' => true, 'message' => 'PHP profili uygulandı.'];
    }

    /**
     * @return array<string, mixed>
     */
    private function serviceHealth(): array
    {
        $version = PHP_VERSION;
        $fpmBinary = trim((string) @shell_exec('command -v php-fpm php-fpm8.4 php-fpm8.3 php-fpm8.2 php-fpm8.1 2>/dev/null | head -n 1'));
        $hasProcess = trim((string) @shell_exec("pgrep -f 'php-fpm' >/dev/null 2>&1 && echo running || echo stopped"));
        $systemd = trim((string) @shell_exec("systemctl list-units --type=service --all 'php*-fpm.service' --no-legend 2>/dev/null | awk 'NR==1{print $4}'"));

        if ($fpmBinary === '' && $hasProcess !== 'running' && $systemd === '') {
            return ['ok' => false, 'status' => 'not_installed', 'service' => 'php-fpm', 'version' => 'PHP ' . $version];
        }

        $running = $hasProcess === 'running' || $systemd === 'running';
        return [
            'ok' => $running,
            'status' => $running ? 'running' : 'stopped',
            'service' => 'php-fpm',
            'version' => 'PHP ' . $version,
        ];
    }
}
