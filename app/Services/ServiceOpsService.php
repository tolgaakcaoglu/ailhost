<?php

declare(strict_types=1);

namespace Ailhost\Services;

use Ailhost\Adapters\DnsAdapter;
use Ailhost\Adapters\DockerAdapter;
use Ailhost\Adapters\MariaDbAdapter;
use Ailhost\Adapters\NginxAdapter;
use Ailhost\Adapters\PhpAdapter;

final class ServiceOpsService
{
    public function __construct(
        private readonly JobService $jobService,
        private readonly NginxAdapter $nginxAdapter,
        private readonly PhpAdapter $phpAdapter,
        private readonly MariaDbAdapter $mariaDbAdapter,
        private readonly DnsAdapter $dnsAdapter,
        private readonly DockerAdapter $dockerAdapter,
        private readonly JsonStateStore $jsonStateStore = new JsonStateStore()
    ) {
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function health(): array
    {
        $states = $this->readStates();
        $map = [
            'nginx' => $this->nginxAdapter->execute(['action' => 'service_health']),
            'php-fpm' => $this->phpAdapter->execute(['action' => 'service_health']),
            'mariadb' => $this->mariaDbAdapter->execute(['action' => 'service_health']),
            'dns' => $this->dnsAdapter->execute(['action' => 'service_health']),
            'docker' => $this->dockerAdapter->execute(['action' => 'service_health']),
        ];

        $rows = [];
        foreach ($map as $service => $result) {
            $status = (string) ($result['status'] ?? 'unknown');
            if (isset($states[$service]['status']) && is_string($states[$service]['status'])) {
                $status = (string) $states[$service]['status'];
            }
            $rows[] = [
                'service' => $service,
                'status' => $status,
                'version' => (string) ($result['version'] ?? ''),
                'message' => (string) ($result['message'] ?? ''),
                'updated_at' => (string) ($states[$service]['updated_at'] ?? '-'),
            ];
        }
        return $rows;
    }

    public function requestControl(string $service, string $action): array
    {
        $allowedServices = ['nginx', 'php-fpm', 'mariadb', 'dns', 'docker'];
        $allowedActions = ['start', 'stop', 'restart'];
        if (!in_array($service, $allowedServices, true)) {
            return ['ok' => false, 'message' => 'Geçersiz servis.'];
        }
        if (!in_array($action, $allowedActions, true)) {
            return ['ok' => false, 'message' => 'Geçersiz aksiyon.'];
        }
        return $this->jobService->enqueue('service_control', [
            'service' => $service,
            'action' => $action,
        ]);
    }

    public function requestPackageInstall(string $service): array
    {
        $packages = $this->packagesForService($service);
        if ($packages === []) {
            return ['ok' => false, 'message' => 'Bu servis için paket kurulumu tanımlı değil.'];
        }

        $created = [];
        foreach ($packages as $package) {
            $result = $this->jobService->enqueue('install_package', [
                'package' => $package,
                'service' => $service,
                'source' => 'dashboard',
            ]);
            if (($result['ok'] ?? false) !== true) {
                return ['ok' => false, 'message' => (string) ($result['message'] ?? 'Paket kurulum işi oluşturulamadı.')];
            }
            $created[] = (string) (($result['job']['id'] ?? ''));
        }

        return ['ok' => true, 'message' => 'Paket kurulum işi kuyruğa alındı.', 'jobs' => $created];
    }

    public function requestPackageRemove(string $service): array
    {
        $packages = $this->packagesForService($service);
        if ($packages === []) {
            return ['ok' => false, 'message' => 'Bu servis için paket kaldırma tanımlı değil.'];
        }

        $created = [];
        foreach (array_reverse($packages) as $package) {
            $result = $this->jobService->enqueue('remove_package', [
                'package' => $package,
                'service' => $service,
                'source' => 'dashboard',
            ]);
            if (($result['ok'] ?? false) !== true) {
                return ['ok' => false, 'message' => (string) ($result['message'] ?? 'Paket kaldırma işi oluşturulamadı.')];
            }
            $created[] = (string) (($result['job']['id'] ?? ''));
        }

        return ['ok' => true, 'message' => 'Paket kaldırma işi kuyruğa alındı.', 'jobs' => $created];
    }

    public function requestDockerImagePull(string $image): array
    {
        $image = trim($image);
        if ($image === '' || !preg_match('/^[a-z0-9][a-z0-9._\\/-]{0,180}(?::[a-zA-Z0-9._-]{1,64})?$/', $image)) {
            return ['ok' => false, 'message' => 'Geçersiz image adı.'];
        }
        return $this->jobService->enqueue('docker_image_pull', ['image' => $image]);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function dockerContainers(): array
    {
        $result = $this->dockerAdapter->execute(['action' => 'container_list']);
        if (($result['ok'] ?? false) !== true) {
            return [];
        }
        $rows = is_array($result['containers'] ?? null) ? $result['containers'] : [];
        return array_values(array_filter($rows, static fn(mixed $row): bool => is_array($row)));
    }

    public function dockerLogs(string $container, int $lines = 80): array
    {
        return $this->dockerAdapter->execute([
            'action' => 'container_logs',
            'container' => $container,
            'lines' => max(10, min(300, $lines)),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function packagesForService(string $service): array
    {
        $map = [
            'nginx' => ['nginx'],
            'php-fpm' => ['php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '-fpm'],
            'mariadb' => ['mariadb-server'],
            'dns' => ['bind9'],
            'docker' => ['docker.io'],
        ];

        return $map[$service] ?? [];
    }

    public function processDockerImagePull(string $image): array
    {
        return $this->dockerAdapter->execute(['action' => 'image_pull', 'image' => $image]);
    }

    public function applyControl(string $service, string $action): array
    {
        $status = $action === 'stop' ? 'stopped' : 'running';
        $states = $this->readStates();
        $states[$service] = [
            'service' => $service,
            'status' => $status,
            'last_action' => $action,
            'updated_at' => date(DATE_ATOM),
        ];
        $ok = $this->writeStates($states);
        return $ok ? ['ok' => true, 'status' => $status] : ['ok' => false, 'message' => 'Servis state yazılamadı.'];
    }

    public function clearState(string $service): void
    {
        $states = $this->readStates();
        unset($states[$service]);
        $this->writeStates($states);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function readStates(): array
    {
        $file = AILHOST_ROOT . '/var/service_states.json';
        $decoded = $this->jsonStateStore->readArray($file);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, array<string, mixed>> $states
     */
    private function writeStates(array $states): bool
    {
        return $this->jsonStateStore->writeArray(AILHOST_ROOT . '/var/service_states.json', $states);
    }
}
