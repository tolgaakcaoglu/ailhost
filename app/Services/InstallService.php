<?php

declare(strict_types=1);

namespace Ailhost\Services;

final class InstallService
{
    public function __construct(
        private readonly array $paths,
        private readonly JobService $jobService,
        private readonly JsonStateStore $jsonStateStore = new JsonStateStore()
    )
    {
    }

    public function isInstalled(): bool
    {
        $markerFile = $this->paths['local_var_path'] . '/installed.lock';
        return is_file($markerFile);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function requirements(): array
    {
        $localVar = $this->paths['local_var_path'];
        $minPhp = '8.1.0';
        $os = $this->osInfo();
        $diskFreeGb = $this->diskFreeGb(AILHOST_ROOT);
        $ramMb = $this->memoryMb();
        $cpuCores = $this->cpuCoreCount();
        $isRoot = $this->isRootUser();
        $ports = $this->portStates([80, 443, 53, 25]);
        $hasSystemctl = $this->commandExists('systemctl');
        $hasNginx = $this->commandExists('nginx');
        $hasDocker = $this->commandExists('docker');
        $dockerVersion = $hasDocker ? $this->commandOutput('docker --version 2>/dev/null') : '';
        $nginxVersion = $hasNginx ? $this->commandOutput('nginx -v 2>&1') : '';
        $mailStatus = $this->mailStatus();

        return [
            [
                'name' => 'PHP >= ' . $minPhp,
                'ok' => version_compare(PHP_VERSION, $minPhp, '>='),
                'current' => PHP_VERSION,
                'critical' => true,
                'hint' => 'Minimum PHP sürümü karşılanmalı.',
            ],
            [
                'name' => 'PDO eklentisi',
                'ok' => extension_loaded('pdo'),
                'current' => extension_loaded('pdo') ? 'yüklü' : 'eksik',
                'critical' => true,
                'hint' => 'PDO aktif olmalı.',
            ],
            [
                'name' => 'Yazılabilir yerel veri dizini',
                'ok' => is_dir($localVar) && is_writable($localVar),
                'current' => $localVar,
                'critical' => true,
                'hint' => 'Dizin var ve yazılabilir olmalı.',
            ],
            [
                'name' => 'İşletim sistemi',
                'ok' => (bool) ($os['supported'] ?? false),
                'current' => (string) ($os['current'] ?? 'tespit edilemedi'),
                'critical' => true,
                'hint' => 'Ubuntu, Debian, AlmaLinux, Rocky Linux, RHEL ve CentOS desteklenir.',
            ],
            [
                'name' => 'Disk boş alan (>= 2 GB)',
                'ok' => $diskFreeGb >= 2.0,
                'current' => number_format($diskFreeGb, 2) . ' GB',
                'critical' => true,
                'hint' => 'Kurulum ve yedekler için boş alan gerekir.',
            ],
            [
                'name' => 'RAM (>= 1024 MB)',
                'ok' => $ramMb >= 1024,
                'current' => $ramMb > 0 ? ((string) $ramMb . ' MB') : 'tespit edilemedi',
                'critical' => true,
                'hint' => 'Düşük RAM işleyişi bozar.',
            ],
            [
                'name' => 'CPU çekirdeği (>= 1)',
                'ok' => $cpuCores >= 1,
                'current' => $cpuCores > 0 ? (string) $cpuCores : 'tespit edilemedi',
                'critical' => false,
                'hint' => 'En az 1 çekirdek önerilir.',
            ],
            [
                'name' => 'Kurulum yetkisi (root)',
                'ok' => $isRoot,
                'current' => $isRoot ? 'root' : 'root değil',
                'critical' => false,
                'hint' => 'Paket/servis kurulumları için root gerekir.',
            ],
            [
                'name' => 'systemctl komutu',
                'ok' => $hasSystemctl,
                'current' => $hasSystemctl ? 'var' : 'yok',
                'critical' => false,
                'hint' => 'Servis yönetimi için önerilir.',
            ],
            [
                'name' => 'nginx komutu',
                'ok' => $hasNginx,
                'current' => $hasNginx ? ($nginxVersion !== '' ? $nginxVersion : 'var') : 'yok',
                'critical' => false,
                'hint' => 'Nginx seçeneği için gereklidir.',
            ],
            [
                'name' => 'Docker',
                'ok' => $hasDocker,
                'current' => $hasDocker ? ($dockerVersion !== '' ? $dockerVersion : 'var') : 'yüklü değil',
                'critical' => false,
                'hint' => 'Container ve tek tık uygulamalar için önerilir.',
            ],
            [
                'name' => 'E-posta Servisi',
                'ok' => (bool) ($mailStatus['ok'] ?? false),
                'current' => (string) ($mailStatus['current'] ?? 'tespit edilemedi'),
                'critical' => false,
                'hint' => 'Mailbox ve gönderim işlemleri için Postfix/Exim ve Dovecot önerilir.',
            ],
            [
                'name' => 'Port 80 durumu',
                'ok' => true,
                'current' => $ports[80] ? 'dinleniyor' : 'boş',
                'critical' => false,
                'hint' => 'Web trafiği için kullanılır.',
            ],
            [
                'name' => 'Port 443 durumu',
                'ok' => true,
                'current' => $ports[443] ? 'dinleniyor' : 'boş',
                'critical' => false,
                'hint' => 'HTTPS trafiği için kullanılır.',
            ],
        ];
    }

    public function allRequirementsOk(): bool
    {
        foreach ($this->requirements() as $requirement) {
            if (($requirement['critical'] ?? false) === true && ($requirement['ok'] ?? false) !== true) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function state(): array
    {
        $file = $this->stateFile();
        if (!is_file($file)) {
            return [
                'admin_created' => false,
                'settings_saved' => false,
                'services_saved' => false,
            ];
        }

        $decoded = $this->jsonStateStore->readArray($file);
        if (!is_array($decoded)) {
            return [
                'admin_created' => false,
                'settings_saved' => false,
                'services_saved' => false,
            ];
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $state
     */
    private function persistState(array $state): bool
    {
        return $this->jsonStateStore->writeArray($this->stateFile(), $state);
    }

    public function saveAdmin(string $email, string $password, string $name = ''): array
    {
        $email = trim($email);
        $name = trim($name);
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return ['ok' => false, 'message' => 'Geçerli bir e-posta gerekli.'];
        }

        if (strlen($password) < 8) {
            return ['ok' => false, 'message' => 'Şifre en az 8 karakter olmalı.'];
        }

        $adminData = [
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'owner',
            'created_at' => date(DATE_ATOM),
        ];

        $writeOk = $this->jsonStateStore->writeArray($this->paths['local_var_path'] . '/admin.json', $adminData);

        if (!$writeOk) {
            return ['ok' => false, 'message' => 'Yönetici bilgileri kaydedilemedi.'];
        }

        $state = $this->state();
        $state['admin_created'] = true;
        $this->persistState($state);

        return ['ok' => true, 'message' => 'Yönetici kaydedildi.'];
    }

    /**
     * @return array<string, mixed>
     */
    public function adminData(): array
    {
        $file = $this->paths['local_var_path'] . '/admin.json';
        if (!is_file($file)) {
            return [];
        }

        $decoded = $this->jsonStateStore->readArray($file);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $options
     */
    public function saveSettings(string $hostname, string $serverIp, string $nameserver1, string $nameserver2, array $options = []): array
    {
        $hostname = trim($hostname);
        $serverIp = trim($serverIp);
        $nameserver1 = trim($nameserver1);
        $nameserver2 = trim($nameserver2);
        $timezone = trim((string) ($options['timezone'] ?? 'UTC'));
        $logRetentionDays = max(1, min(365, (int) ($options['log_retention_days'] ?? 30)));
        $defaultWebRoot = rtrim(trim((string) ($options['default_web_root'] ?? '/var/www/html')), '/');
        $backupPath = rtrim(trim((string) ($options['backup_path'] ?? '/mnt/backups/ailhost')), '/');
        $allowCreateWebRoot = (bool) ($options['create_default_web_root'] ?? false);
        $allowCreateBackupPath = (bool) ($options['create_backup_path'] ?? false);
        $sudoPassword = trim((string) ($options['sudo_password'] ?? ''));

        if ($hostname === '') {
            return ['ok' => false, 'message' => 'Server adı gerekli.'];
        }

        if ($serverIp === '') {
            $serverIp = '127.0.0.1';
        }

        if (filter_var($serverIp, FILTER_VALIDATE_IP) === false) {
            return ['ok' => false, 'message' => 'Sunucu IP adresi geçersiz.'];
        }

        if ($defaultWebRoot === '' || !str_starts_with($defaultWebRoot, '/')) {
            return ['ok' => false, 'message' => 'Varsayılan web dizini mutlak path olmalı.'];
        }

        if ($backupPath === '' || !str_starts_with($backupPath, '/')) {
            return ['ok' => false, 'message' => 'Yedekleme dizini mutlak path olmalı.'];
        }

        $webRootCheck = $this->ensureDirectoryExists($defaultWebRoot, $allowCreateWebRoot, $sudoPassword);
        if (($webRootCheck['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => (string) ($webRootCheck['message'] ?? 'Varsayılan web dizini kontrolü başarısız.')];
        }

        $backupPathCheck = $this->ensureDirectoryExists($backupPath, $allowCreateBackupPath, $sudoPassword);
        if (($backupPathCheck['ok'] ?? false) !== true) {
            return ['ok' => false, 'message' => (string) ($backupPathCheck['message'] ?? 'Yedekleme dizini kontrolü başarısız.')];
        }

        $settings = [
            'hostname' => $hostname,
            'server_name' => $hostname,
            'server_ip' => $serverIp,
            'nameserver_1' => $nameserver1,
            'nameserver_2' => $nameserver2,
            'timezone' => $timezone !== '' ? $timezone : 'UTC',
            'log_retention_days' => $logRetentionDays,
            'default_web_root' => $defaultWebRoot,
            'backup_path' => $backupPath,
            'updated_at' => date(DATE_ATOM),
        ];

        $writeOk = $this->jsonStateStore->writeArray($this->paths['local_var_path'] . '/settings.json', $settings);

        if (!$writeOk) {
            return ['ok' => false, 'message' => 'Ayarlar kaydedilemedi.'];
        }

        $state = $this->state();
        $state['settings_saved'] = true;
        $this->persistState($state);
        $this->appendLog('Ayarlar kaydedildi.');

        return ['ok' => true, 'message' => 'Ayarlar kaydedildi.'];
    }

    /**
     * @return array{ok: bool, suggestions: array<int, string>, children_map: array<string, bool>, can_create: bool, create_candidate: string}
     */
    public function directorySuggestions(string $input): array
    {
        $input = trim(preg_replace('#/+#', '/', $input) ?? $input);
        if ($input === '') {
            return [
                'ok' => true,
                'suggestions' => ['/var', '/home', '/srv', '/mnt'],
                'children_map' => ['/var' => true, '/home' => true, '/srv' => true, '/mnt' => true],
                'can_create' => false,
                'create_candidate' => '',
            ];
        }

        if (!str_starts_with($input, '/')) {
            return ['ok' => true, 'suggestions' => [], 'children_map' => [], 'can_create' => false, 'create_candidate' => ''];
        }

        $parent = '/';
        $fragment = '';
        if (str_ends_with($input, '/')) {
            $parent = rtrim($input, '/');
            $parent = $parent === '' ? '/' : $parent;
        } else {
            $parent = dirname($input);
            $parent = $parent === '.' ? '/' : $parent;
            $fragment = basename($input);
        }

        if (!is_dir($parent) || !is_readable($parent)) {
            $nearest = $this->nearestExistingDirectory($input);
            return [
                'ok' => true,
                'suggestions' => [],
                'children_map' => [],
                'can_create' => $nearest !== null,
                'create_candidate' => $nearest !== null ? $input : '',
            ];
        }

        $suggestions = [];
        $childrenMap = [];
        $entries = scandir($parent);
        if (is_array($entries)) {
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                if ($fragment !== '' && !str_starts_with(strtolower($entry), strtolower($fragment))) {
                    continue;
                }
                $candidate = ($parent === '/' ? '/' : $parent . '/') . $entry;
                if (is_dir($candidate)) {
                    $suggestions[] = $candidate;
                    $childrenMap[$candidate] = $this->directoryHasChildren($candidate);
                }
            }
        }
        sort($suggestions, SORT_NATURAL | SORT_FLAG_CASE);
        $suggestions = array_slice($suggestions, 0, 15);
        $canCreate = $fragment !== '' && $suggestions === [];

        return [
            'ok' => true,
            'suggestions' => $suggestions,
            'children_map' => $childrenMap,
            'can_create' => $canCreate,
            'create_candidate' => $canCreate ? $input : '',
        ];
    }

    public function saveServices(string $webServer, string $dnsServer, string $firewall): array
    {
        $state = $this->state();
        if (($state['admin_created'] ?? false) !== true || ($state['settings_saved'] ?? false) !== true) {
            return ['ok' => false, 'message' => 'Önce yönetici ve sunucu ayarları adımları tamamlanmalı.'];
        }

        $webServer = trim($webServer);
        $dnsServer = trim($dnsServer);
        $firewall = trim($firewall);

        $allowedWeb = ['nginx', 'openlitespeed'];
        $allowedDns = ['bind', 'powerdns'];
        $allowedFirewall = ['firewalld', 'none'];

        if (!in_array($webServer, $allowedWeb, true)) {
            return ['ok' => false, 'message' => 'Geçersiz web sunucusu seçimi.'];
        }
        if (!in_array($dnsServer, $allowedDns, true)) {
            return ['ok' => false, 'message' => 'Geçersiz DNS sunucusu seçimi.'];
        }
        if (!in_array($firewall, $allowedFirewall, true)) {
            return ['ok' => false, 'message' => 'Geçersiz firewall seçimi.'];
        }

        $services = [
            'web_server' => $webServer,
            'dns_server' => $dnsServer,
            'firewall' => $firewall,
            'updated_at' => date(DATE_ATOM),
        ];

        $writeOk = $this->jsonStateStore->writeArray($this->paths['local_var_path'] . '/services.json', $services);

        if (!$writeOk) {
            return ['ok' => false, 'message' => 'Servis seçimleri kaydedilemedi.'];
        }

        $state = $this->state();
        $state['services_saved'] = true;
        $this->persistState($state);

        $job = $this->jobService->enqueue('service_selection', [
            'web_server' => $webServer,
            'dns_server' => $dnsServer,
            'firewall' => $firewall,
        ]);

        if (($job['ok'] ?? false) === false) {
            return ['ok' => false, 'message' => 'Servis seçimi kaydedildi ancak iş kaydı oluşturulamadı.'];
        }

        $packages = $this->packagesFromSelection($webServer, $dnsServer);
        foreach ($packages as $package) {
            $this->jobService->enqueue('install_package', [
                'package' => $package,
                'source' => 'installer',
            ]);
        }

        $this->jobService->appendLog((string) (($job['job']['id'] ?? 'system')), 'info', 'Servis seçimleri kaydedildi.');
        return ['ok' => true, 'message' => 'Servis seçimleri kaydedildi.'];
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsData(): array
    {
        $file = $this->paths['local_var_path'] . '/settings.json';
        if (!is_file($file)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($file), true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function servicesData(): array
    {
        $file = $this->paths['local_var_path'] . '/services.json';
        if (!is_file($file)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($file), true);
        return is_array($decoded) ? $decoded : [];
    }

    public function lockInstall(): array
    {
        $state = $this->state();
        if (
            ($state['admin_created'] ?? false) !== true ||
            ($state['settings_saved'] ?? false) !== true
        ) {
            return ['ok' => false, 'message' => 'Kurulumu bitirmeden önce ağ ayarları ve yönetici tamamlanmalı.'];
        }

        if (($state['services_saved'] ?? false) !== true) {
            $this->ensureDefaultServices();
        }

        $markerFile = $this->paths['local_var_path'] . '/installed.lock';
        $writeOk = file_put_contents($markerFile, 'installed_at=' . date(DATE_ATOM) . PHP_EOL) !== false;

        if (!$writeOk) {
            return ['ok' => false, 'message' => 'Kurulum kilidi oluşturulamadı.'];
        }

        $this->jobService->appendLog('system', 'info', 'Kurulum kilitlendi.');
        return ['ok' => true, 'message' => 'Kurulum kilitlendi.'];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function jobs(): array
    {
        return $this->jobService->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function logs(): array
    {
        return $this->jobService->logs();
    }

    public function retryLastFailedJob(): array
    {
        return $this->jobService->retryLastFailed();
    }

    private function appendLog(string $message): void
    {
        $this->jobService->appendLog('system', 'info', $message);
    }

    private function ensureDefaultServices(): void
    {
        $services = [
            'web_server' => 'nginx',
            'dns_server' => 'bind',
            'firewall' => 'firewalld',
            'source' => 'guided_installer_default',
            'updated_at' => date(DATE_ATOM),
        ];

        if ($this->jsonStateStore->writeArray($this->paths['local_var_path'] . '/services.json', $services)) {
            $state = $this->state();
            $state['services_saved'] = true;
            $this->persistState($state);
            $this->appendLog('Varsayılan servis seçimi kaydedildi.');
        }
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    private function ensureDirectoryExists(string $path, bool $allowCreate, string $sudoPassword = ''): array
    {
        if (is_dir($path)) {
            return ['ok' => true];
        }

        if (file_exists($path) && !is_dir($path)) {
            return ['ok' => false, 'message' => 'Dizin yolu bir dosyaya işaret ediyor: ' . $path];
        }

        if (!$allowCreate) {
            return ['ok' => false, 'message' => 'Dizin bulunamadı: ' . $path];
        }

        $created = @mkdir($path, 0775, true);
        if (!$created && !is_dir($path)) {
            if ($sudoPassword === '') {
                return ['ok' => false, 'message' => 'Dizin oluşturulamadı: ' . $path . '. Root izni gerekiyorsa sudo şifresi girin.'];
            }

            $sudoCreated = $this->createDirectoryWithSudo($path, $sudoPassword);
            if (($sudoCreated['ok'] ?? false) !== true) {
                return ['ok' => false, 'message' => (string) ($sudoCreated['message'] ?? ('Dizin oluşturulamadı: ' . $path))];
            }
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    private function createDirectoryWithSudo(string $path, string $password): array
    {
        if (!function_exists('proc_open')) {
            return ['ok' => false, 'message' => 'Sudo işlemi desteklenmiyor (proc_open kapalı).'];
        }

        $owner = function_exists('posix_getpwuid') ? (string) ((posix_getpwuid(posix_geteuid())['name'] ?? '') ?: '') : '';
        $group = function_exists('posix_getgrgid') ? (string) ((posix_getgrgid(posix_getegid())['name'] ?? '') ?: '') : '';
        $ownerGroup = ($owner !== '' && $group !== '') ? ($owner . ':' . $group) : '';

        $commands = ['mkdir -p ' . escapeshellarg($path)];
        if ($ownerGroup !== '') {
            $commands[] = 'chown -R ' . escapeshellarg($ownerGroup) . ' ' . escapeshellarg($path);
        }
        $commands[] = 'chmod -R 775 ' . escapeshellarg($path);

        $cmd = 'sudo -S -p "" sh -c ' . escapeshellarg(implode(' && ', $commands));
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptorSpec, $pipes);
        if (!is_resource($process)) {
            return ['ok' => false, 'message' => 'Sudo işlemi başlatılamadı.'];
        }

        fwrite($pipes[0], $password . PHP_EOL);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]) ?: '';
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            $error = trim($stderr !== '' ? $stderr : $stdout);
            return ['ok' => false, 'message' => 'Sudo ile dizin oluşturulamadı: ' . ($error !== '' ? $error : 'Hata kodu ' . $exitCode)];
        }

        if (!is_dir($path)) {
            return ['ok' => false, 'message' => 'Sudo komutu çalıştı ancak dizin doğrulanamadı: ' . $path];
        }

        return ['ok' => true];
    }

    private function directoryHasChildren(string $path): bool
    {
        if (!is_dir($path) || !is_readable($path)) {
            return false;
        }
        $entries = scandir($path);
        if (!is_array($entries)) {
            return false;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (is_dir(rtrim($path, '/') . '/' . $entry)) {
                return true;
            }
        }
        return false;
    }

    private function nearestExistingDirectory(string $path): ?string
    {
        $path = trim($path);
        if ($path === '' || !str_starts_with($path, '/')) {
            return null;
        }

        $cursor = $path;
        while ($cursor !== '/' && $cursor !== '.' && $cursor !== '') {
            if (is_dir($cursor)) {
                return $cursor;
            }
            $cursor = dirname($cursor);
        }

        return is_dir('/') ? '/' : null;
    }

    /**
     * @return array{current: string, supported: bool}
     */
    private function osInfo(): array
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return [
                'current' => PHP_OS_FAMILY !== '' ? PHP_OS_FAMILY : php_uname('s'),
                'supported' => false,
            ];
        }

        $id = '';
        $idLike = '';
        $prettyName = '';
        if (is_readable('/etc/os-release')) {
            $lines = file('/etc/os-release', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (is_array($lines)) {
                foreach ($lines as $line) {
                    if (str_starts_with($line, 'ID=')) {
                        $id = strtolower(trim(substr($line, 3), "\"' \t"));
                    }
                    if (str_starts_with($line, 'ID_LIKE=')) {
                        $idLike = strtolower(trim(substr($line, 8), "\"' \t"));
                    }
                    if (str_starts_with($line, 'PRETTY_NAME=')) {
                        $prettyName = trim(substr($line, 12), "\"' \t");
                    }
                }
            }
        }

        $supportedIds = ['ubuntu', 'debian', 'almalinux', 'alma', 'rocky', 'rhel', 'centos'];
        $tokens = array_filter(array_merge([$id], preg_split('/\s+/', $idLike) ?: []));
        $supported = count(array_intersect($supportedIds, $tokens)) > 0;

        return [
            'current' => $prettyName !== '' ? $prettyName : php_uname('s'),
            'supported' => $supported,
        ];
    }

    private function diskFreeGb(string $path): float
    {
        $bytes = @disk_free_space($path);
        if ($bytes === false) {
            return 0.0;
        }
        return $bytes / 1024 / 1024 / 1024;
    }

    private function memoryMb(): int
    {
        if (!is_readable('/proc/meminfo')) {
            return 0;
        }
        $content = (string) file_get_contents('/proc/meminfo');
        if (preg_match('/^MemTotal:\s+(\d+)\skB/im', $content, $m) !== 1) {
            return 0;
        }
        return (int) floor(((int) $m[1]) / 1024);
    }

    private function cpuCoreCount(): int
    {
        if (is_readable('/proc/cpuinfo')) {
            $content = (string) file_get_contents('/proc/cpuinfo');
            preg_match_all('/^processor\s*:/im', $content, $m);
            $count = count($m[0] ?? []);
            if ($count > 0) {
                return $count;
            }
        }
        return 0;
    }

    private function isRootUser(): bool
    {
        if (function_exists('posix_geteuid')) {
            return posix_geteuid() === 0;
        }
        return false;
    }

    /**
     * @param array<int, int> $ports
     * @return array<int, bool>
     */
    private function portStates(array $ports): array
    {
        $states = [];
        foreach ($ports as $port) {
            $conn = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.25);
            $states[$port] = is_resource($conn);
            if (is_resource($conn)) {
                fclose($conn);
            }
        }
        return $states;
    }

    private function commandExists(string $command): bool
    {
        $output = @shell_exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null');
        return trim((string) $output) !== '';
    }

    private function commandOutput(string $command): string
    {
        $output = @shell_exec($command);
        return trim(preg_replace('/\s+/', ' ', (string) $output) ?? '');
    }

    /**
     * @return array{ok: bool, current: string}
     */
    private function mailStatus(): array
    {
        $postfix = $this->commandExists('postfix') || $this->commandExists('postconf');
        $exim = $this->commandExists('exim') || $this->commandExists('exim4');
        $dovecot = $this->commandExists('dovecot');
        $parts = [];

        if ($postfix) {
            $parts[] = 'Postfix';
        }
        if ($exim) {
            $parts[] = 'Exim';
        }
        if ($dovecot) {
            $parts[] = 'Dovecot';
        }

        if ($parts === []) {
            return ['ok' => false, 'current' => 'yüklü değil'];
        }

        return [
            'ok' => ($postfix || $exim) && $dovecot,
            'current' => implode(' + ', $parts),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function packagesFromSelection(string $webServer, string $dnsServer): array
    {
        $map = [
            'nginx' => ['nginx'],
            'openlitespeed' => ['openlitespeed'],
            'bind' => ['bind9'],
            'powerdns' => ['powerdns'],
        ];
        $base = ['php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '-fpm', 'mariadb-server'];
        $selected = array_merge($base, $map[$webServer] ?? [], $map[$dnsServer] ?? []);
        return array_values(array_unique($selected));
    }

    private function stateFile(): string
    {
        return $this->paths['local_var_path'] . '/install_state.json';
    }
}
