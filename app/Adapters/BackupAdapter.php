<?php

declare(strict_types=1);

namespace Ailhost\Adapters;

use Ailhost\Services\JsonStateStore;

final class BackupAdapter implements SystemAdapterInterface
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
        if (!in_array($action, ['create', 'restore', 'restore_dry_run'], true)) {
            return ['ok' => false, 'message' => 'Desteklenmeyen backup işlemi.'];
        }

        $siteId = (string) ($payload['site_id'] ?? '');
        if ($siteId === '') {
            return ['ok' => false, 'message' => 'Site bilgisi eksik.'];
        }

        $dir = $this->projectRoot . '/var/backups/' . $siteId;
        if (!is_dir($dir) && !mkdir($concurrentDirectory = $dir, 0755, true) && !is_dir($concurrentDirectory)) {
            return ['ok' => false, 'message' => 'Backup dizini oluşturulamadı.'];
        }

        if ($action === 'create') {
            $backupId = (string) ($payload['backup_id'] ?? bin2hex(random_bytes(8)));
            $file = $dir . '/' . $backupId . '.tar.gz';
            $meta = [
                'site_id' => $siteId,
                'backup_id' => $backupId,
                'type' => (string) ($payload['type'] ?? 'full'),
                'created_at' => date(DATE_ATOM),
            ];
            if (!$this->jsonStateStore->writeArray($file, $meta)) {
                return ['ok' => false, 'message' => 'Backup dosyası yazılamadı.'];
            }
            $size = filesize($file);
            if ($size === false) {
                return ['ok' => false, 'message' => 'Backup dosya boyutu okunamadı.'];
            }
            $checksum = hash_file('sha256', $file);
            if ($checksum === false) {
                return ['ok' => false, 'message' => 'Backup checksum üretilemedi.'];
            }
            return [
                'ok' => true,
                'file' => $file,
                'size_bytes' => (int) $size,
                'checksum_sha256' => $checksum,
            ];
        }

        $backupId = (string) ($payload['backup_id'] ?? '');
        if ($backupId === '') {
            return ['ok' => false, 'message' => 'Backup kimliği eksik.'];
        }
        $file = $dir . '/' . $backupId . '.tar.gz';
        if (!is_file($file)) {
            return ['ok' => false, 'message' => 'Backup dosyası bulunamadı.'];
        }
        if ($action === 'restore_dry_run') {
            return ['ok' => true, 'message' => 'Dry-run başarılı. Restore ön kontrolü geçti.'];
        }
        return ['ok' => true, 'message' => 'Restore işlemi simüle edildi.'];
    }
}
