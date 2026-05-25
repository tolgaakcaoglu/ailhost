<?php

declare(strict_types=1);

namespace Ailhost\Adapters;

use Ailhost\Services\JsonStateStore;

final class PackageAdapter implements SystemAdapterInterface
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
        if (!in_array($action, ['ensure_installed', 'ensure_removed'], true)) {
            return ['ok' => false, 'message' => 'Desteklenmeyen paket işlemi.'];
        }

        $package = trim((string) ($payload['package'] ?? ''));
        if ($package === '') {
            return ['ok' => false, 'message' => 'Paket adı gerekli.'];
        }

        if ($action === 'ensure_removed') {
            return $this->ensureRemoved($package);
        }

        $state = $this->readState();
        if ($this->isInstalled($package)) {
            $this->markVerified($state, $package);
            return ['ok' => true, 'message' => 'Paket zaten kurulu.', 'package' => $package, 'changed' => false];
        }

        $install = $this->install($package);
        if (($install['ok'] ?? false) !== true) {
            return [
                'ok' => false,
                'message' => (string) ($install['message'] ?? 'Paket kurulamadı.'),
                'package' => $package,
                'changed' => false,
            ];
        }

        if (!$this->isInstalled($package)) {
            return ['ok' => false, 'message' => 'Paket kurulumu doğrulanamadı.', 'package' => $package, 'changed' => false];
        }

        $this->markVerified($state, $package);
        return ['ok' => true, 'message' => 'Paket kuruldu ve doğrulandı.', 'package' => $package, 'changed' => true];
    }

    /**
     * @return array<string, mixed>
     */
    private function ensureRemoved(string $package): array
    {
        $state = $this->readState();
        if (!$this->isInstalled($package)) {
            $this->unmarkVerified($state, $package);
            return ['ok' => true, 'message' => 'Paket zaten sistemde yok.', 'package' => $package, 'changed' => false];
        }

        $remove = $this->remove($package);
        if (($remove['ok'] ?? false) !== true) {
            return [
                'ok' => false,
                'message' => (string) ($remove['message'] ?? 'Paket kaldırılamadı.'),
                'package' => $package,
                'changed' => false,
            ];
        }

        if ($this->isInstalled($package)) {
            return ['ok' => false, 'message' => 'Paket kaldırma işlemi doğrulanamadı.', 'package' => $package, 'changed' => false];
        }

        $this->unmarkVerified($state, $package);
        return ['ok' => true, 'message' => 'Paket sistemden kaldırıldı.', 'package' => $package, 'changed' => true];
    }

    private function isInstalled(string $package): bool
    {
        if ($this->commandExists('dpkg-query')) {
            $cmd = 'dpkg-query -W -f=${Status} ' . escapeshellarg($package) . ' 2>/dev/null';
            $output = trim((string) @shell_exec($cmd));
            return str_contains($output, 'install ok installed');
        }

        if ($this->commandExists('rpm')) {
            $cmd = 'rpm -q ' . escapeshellarg($package) . ' >/dev/null 2>&1; echo $?';
            return trim((string) @shell_exec($cmd)) === '0';
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function install(string $package): array
    {
        $prefix = $this->privilegePrefix();
        if ($prefix === null) {
            return ['ok' => false, 'message' => 'Paket kurulumu için root veya parolasız sudo yetkisi gerekli.'];
        }

        if ($this->commandExists('apt-get')) {
            return $this->run($prefix . 'env DEBIAN_FRONTEND=noninteractive apt-get install -y ' . escapeshellarg($package));
        }

        if ($this->commandExists('dnf')) {
            return $this->run($prefix . 'dnf install -y ' . escapeshellarg($package));
        }

        if ($this->commandExists('yum')) {
            return $this->run($prefix . 'yum install -y ' . escapeshellarg($package));
        }

        return ['ok' => false, 'message' => 'Desteklenen paket yöneticisi bulunamadı.'];
    }

    /**
     * @return array<string, mixed>
     */
    private function remove(string $package): array
    {
        $prefix = $this->privilegePrefix();
        if ($prefix === null) {
            return ['ok' => false, 'message' => 'Paket kaldırma için root veya parolasız sudo yetkisi gerekli.'];
        }

        if ($this->commandExists('apt-get')) {
            return $this->run($prefix . 'env DEBIAN_FRONTEND=noninteractive apt-get purge -y ' . escapeshellarg($package));
        }

        if ($this->commandExists('dnf')) {
            return $this->run($prefix . 'dnf remove -y ' . escapeshellarg($package));
        }

        if ($this->commandExists('yum')) {
            return $this->run($prefix . 'yum remove -y ' . escapeshellarg($package));
        }

        return ['ok' => false, 'message' => 'Desteklenen paket yöneticisi bulunamadı.'];
    }

    private function privilegePrefix(): ?string
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            return '';
        }

        if ($this->commandExists('sudo') && trim((string) @shell_exec('sudo -n true 2>/dev/null; echo $?')) === '0') {
            return 'sudo -n ';
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function run(string $command): array
    {
        $output = [];
        $code = 1;
        @exec('timeout 900s ' . $command . ' 2>&1', $output, $code);
        if ($code === 0) {
            return ['ok' => true, 'message' => 'Paket yöneticisi işlemi tamamlandı.'];
        }

        $tail = implode("\n", array_slice($output, -8));
        return ['ok' => false, 'message' => $tail !== '' ? $tail : 'Paket yöneticisi işlemi başarısız.'];
    }

    private function commandExists(string $command): bool
    {
        return trim((string) @shell_exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null')) !== '';
    }

    /**
     * @param array<string, mixed> $state
     */
    private function markVerified(array $state, string $package): void
    {
        $state['installed'] = array_values(array_unique(array_merge(
            array_map('strval', is_array($state['installed'] ?? null) ? $state['installed'] : []),
            [$package]
        )));
        $state['verified_at'][$package] = date(DATE_ATOM);
        $state['updated_at'] = date(DATE_ATOM);
        $this->writeState($state);
    }

    /**
     * @param array<string, mixed> $state
     */
    private function unmarkVerified(array $state, string $package): void
    {
        $installed = array_map('strval', is_array($state['installed'] ?? null) ? $state['installed'] : []);
        $state['installed'] = array_values(array_filter($installed, static fn(string $current): bool => $current !== $package));
        if (is_array($state['verified_at'] ?? null)) {
            unset($state['verified_at'][$package]);
        }
        $state['updated_at'] = date(DATE_ATOM);
        $this->writeState($state);
    }

    /**
     * @return array<string, mixed>
     */
    private function readState(): array
    {
        $file = $this->stateFile();
        $decoded = $this->jsonStateStore->readArray($file);
        if (!is_array($decoded)) {
            return ['installed' => [], 'updated_at' => null];
        }
        if (!is_array($decoded['installed'] ?? null)) {
            $decoded['installed'] = [];
        }
        return $decoded;
    }

    /**
     * @param array<string, mixed> $state
     */
    private function writeState(array $state): bool
    {
        $file = $this->stateFile();
        return $this->jsonStateStore->writeArray($file, $state);
    }

    private function stateFile(): string
    {
        return $this->projectRoot . '/var/system_packages.json';
    }
}
