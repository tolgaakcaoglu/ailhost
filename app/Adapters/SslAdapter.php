<?php

declare(strict_types=1);

namespace Ailhost\Adapters;

use Ailhost\Services\JsonStateStore;

final class SslAdapter implements SystemAdapterInterface
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
        $allowed = ['issue_letsencrypt', 'renew_letsencrypt', 'revoke_certificate'];

        if (!in_array($action, $allowed, true)) {
            return ['ok' => false, 'message' => 'Desteklenmeyen SSL işlemi.'];
        }

        return match ($action) {
            'issue_letsencrypt' => $this->issue($payload),
            'renew_letsencrypt' => $this->renew($payload),
            'revoke_certificate' => $this->revoke($payload),
            default => ['ok' => false, 'message' => 'Desteklenmeyen SSL işlemi.'],
        };
    }

    private function issue(array $payload): array
    {
        $domain = mb_strtolower(trim((string) ($payload['domain'] ?? '')));
        if ($domain === '') {
            return ['ok' => false, 'message' => 'Domain gerekli.'];
        }

        if (!is_dir($this->certDir()) && !mkdir($concurrentDirectory = $this->certDir(), 0755, true) && !is_dir($concurrentDirectory)) {
            return ['ok' => false, 'message' => 'SSL çıktı dizini oluşturulamadı.'];
        }

        $file = $this->certDir() . '/' . $domain . '.json';
        $payload = [
            'domain' => $domain,
            'provider' => 'letsencrypt',
            'status' => 'issued',
            'issued_at' => date(DATE_ATOM),
        ];

        if (!$this->jsonStateStore->writeArray($file, $payload)) {
            return ['ok' => false, 'message' => 'SSL durumu yazılamadı.'];
        }

        return ['ok' => true, 'message' => 'SSL talebi işlendi.', 'certificate_file' => $file];
    }

    private function certDir(): string
    {
        return $this->projectRoot . '/var/generated/ssl';
    }

    private function renew(array $payload): array
    {
        $domain = mb_strtolower(trim((string) ($payload['domain'] ?? '')));
        if ($domain === '') {
            return ['ok' => false, 'message' => 'Domain gerekli.'];
        }
        $file = $this->certDir() . '/' . $domain . '.json';
        $state = [
            'domain' => $domain,
            'provider' => 'letsencrypt',
            'status' => 'renewed',
            'renewed_at' => date(DATE_ATOM),
        ];
        if (is_file($file)) {
            $old = json_decode((string) file_get_contents($file), true);
            if (is_array($old)) {
                $state = array_merge($old, $state);
            }
        }
        if (!$this->jsonStateStore->writeArray($file, $state)) {
            return ['ok' => false, 'message' => 'SSL yenileme durumu yazılamadı.'];
        }
        return ['ok' => true, 'message' => 'SSL yenilendi.'];
    }

    private function revoke(array $payload): array
    {
        $domain = mb_strtolower(trim((string) ($payload['domain'] ?? '')));
        if ($domain === '') {
            return ['ok' => false, 'message' => 'Domain gerekli.'];
        }

        $file = $this->certDir() . '/' . $domain . '.json';
        if (!is_file($file)) {
            return ['ok' => true, 'message' => 'SSL sertifika kaydı zaten yok.'];
        }

        return unlink($file)
            ? ['ok' => true, 'message' => 'SSL sertifika kaydı silindi.']
            : ['ok' => false, 'message' => 'SSL sertifika kaydı silinemedi.'];
    }
}
