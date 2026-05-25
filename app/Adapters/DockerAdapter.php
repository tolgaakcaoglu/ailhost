<?php

declare(strict_types=1);

namespace Ailhost\Adapters;

final class DockerAdapter implements SystemAdapterInterface
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    public function execute(array $payload): array
    {
        $action = (string) ($payload['action'] ?? '');
        if (!in_array($action, ['service_health', 'container_list', 'image_pull', 'container_logs'], true)) {
            return ['ok' => false, 'message' => 'Desteklenmeyen Docker işlemi.'];
        }

        if (!$this->dockerBinaryExists()) {
            return ['ok' => false, 'message' => 'Docker komutu bulunamadı.', 'status' => 'not_installed'];
        }

        return match ($action) {
            'service_health' => $this->serviceHealth(),
            'container_list' => $this->containerList(),
            'image_pull' => $this->imagePull((string) ($payload['image'] ?? '')),
            'container_logs' => $this->containerLogs((string) ($payload['container'] ?? ''), (int) ($payload['lines'] ?? 80)),
            default => ['ok' => false, 'message' => 'Desteklenmeyen Docker işlemi.'],
        };
    }

    private function serviceHealth(): array
    {
        $result = $this->runCommand(['docker', 'info', '--format', '{{.ServerVersion}}']);
        if (($result['ok'] ?? false) !== true) {
            return ['ok' => false, 'status' => 'stopped', 'service' => 'docker', 'message' => (string) ($result['message'] ?? 'Docker info çalıştırılamadı.')];
        }
        return ['ok' => true, 'status' => 'running', 'service' => 'docker', 'version' => trim((string) ($result['output'] ?? ''))];
    }

    /**
     * @return array<string, mixed>
     */
    private function containerList(): array
    {
        $format = '{{.Names}}|{{.Image}}|{{.Status}}|{{.Ports}}';
        $result = $this->runCommand(['docker', 'ps', '-a', '--format', $format]);
        if (($result['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => (string) ($result['message'] ?? 'Container listesi alınamadı.')];
        }

        $rows = [];
        $output = trim((string) ($result['output'] ?? ''));
        if ($output !== '') {
            foreach (explode("\n", $output) as $line) {
                $parts = explode('|', $line);
                $rows[] = [
                    'name' => (string) ($parts[0] ?? ''),
                    'image' => (string) ($parts[1] ?? ''),
                    'status' => (string) ($parts[2] ?? ''),
                    'ports' => (string) ($parts[3] ?? ''),
                ];
            }
        }
        return ['ok' => true, 'containers' => $rows];
    }

    private function imagePull(string $image): array
    {
        $image = trim($image);
        if ($image === '' || !preg_match('/^[a-z0-9][a-z0-9._\\/-]{0,180}(?::[a-zA-Z0-9._-]{1,64})?$/', $image)) {
            return ['ok' => false, 'message' => 'Geçersiz image adı.'];
        }
        $result = $this->runCommand(['docker', 'pull', $image], 300);
        if (($result['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => (string) ($result['message'] ?? 'Image pull başarısız.')];
        }
        return ['ok' => true, 'message' => 'Image pull tamamlandı: ' . $image];
    }

    private function containerLogs(string $container, int $lines): array
    {
        $container = trim($container);
        if ($container === '' || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]{0,120}$/', $container)) {
            return ['ok' => false, 'message' => 'Geçersiz container adı.'];
        }
        $lines = max(10, min(300, $lines));
        $result = $this->runCommand(['docker', 'logs', '--tail', (string) $lines, $container], 30);
        if (($result['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => (string) ($result['message'] ?? 'Container logları alınamadı.')];
        }
        return ['ok' => true, 'logs' => trim((string) ($result['output'] ?? ''))];
    }

    private function dockerBinaryExists(): bool
    {
        $which = @shell_exec('command -v docker 2>/dev/null');
        return is_string($which) && trim($which) !== '';
    }

    /**
     * @param array<int, string> $parts
     * @return array<string, mixed>
     */
    private function runCommand(array $parts, int $timeoutSeconds = 30): array
    {
        $cmd = implode(' ', array_map('escapeshellarg', $parts));
        $full = 'timeout ' . max(1, $timeoutSeconds) . 's ' . $cmd . ' 2>&1';
        $output = @shell_exec($full);
        if (!is_string($output)) {
            return ['ok' => false, 'message' => 'Komut çıktısı okunamadı.'];
        }
        if (str_contains(mb_strtolower($output), 'error') || str_contains(mb_strtolower($output), 'cannot connect')) {
            return ['ok' => false, 'message' => trim($output)];
        }
        return ['ok' => true, 'output' => $output];
    }
}
