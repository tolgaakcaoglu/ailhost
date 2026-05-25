<?php

declare(strict_types=1);

namespace Ailhost\Adapters;

use Ailhost\Services\JsonStateStore;

final class ProcessSupervisorAdapter implements SystemAdapterInterface
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
        $siteId = trim((string) ($payload['site_id'] ?? ''));
        if ($siteId === '') {
            return ['ok' => false, 'message' => 'site_id zorunlu.'];
        }

        $states = $this->readStates();
        $current = is_array($states[$siteId] ?? null) ? $states[$siteId] : [];
        $currentStatus = (string) ($current['status'] ?? 'stopped');

        if (!in_array($action, ['start', 'stop', 'restart', 'status'], true)) {
            return ['ok' => false, 'message' => 'Desteklenmeyen process işlemi.'];
        }

        if ($action === 'status') {
            return ['ok' => true, 'status' => $currentStatus, 'message' => 'Durum okundu.'];
        }

        $startCommand = trim((string) ($payload['start_command'] ?? ''));
        if ($startCommand === '' && $action !== 'stop') {
            return ['ok' => false, 'message' => 'Start komutu zorunlu.'];
        }

        $port = (int) ($payload['port'] ?? 0);
        if ($action !== 'stop' && ($port < 1 || $port > 65535)) {
            return ['ok' => false, 'message' => 'Port geçersiz.'];
        }

        $nextStatus = match ($action) {
            'start' => 'running',
            'stop' => 'stopped',
            'restart' => 'running',
            default => $currentStatus,
        };

        $states[$siteId] = [
            'site_id' => $siteId,
            'status' => $nextStatus,
            'last_action' => $action,
            'start_command' => $startCommand !== '' ? $startCommand : (string) ($current['start_command'] ?? ''),
            'port' => $port > 0 ? $port : (int) ($current['port'] ?? 0),
            'updated_at' => date(DATE_ATOM),
        ];
        if (!$this->writeStates($states)) {
            return ['ok' => false, 'message' => 'Process state yazılamadı.'];
        }

        return [
            'ok' => true,
            'status' => $nextStatus,
            'message' => 'Process işlemi uygulandı: ' . $action,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function readStates(): array
    {
        $decoded = $this->jsonStateStore->readArray($this->stateFile());
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, array<string, mixed>> $states
     */
    private function writeStates(array $states): bool
    {
        return $this->jsonStateStore->writeArray($this->stateFile(), $states);
    }

    private function stateFile(): string
    {
        return $this->projectRoot . '/var/process_supervisor_states.json';
    }
}
