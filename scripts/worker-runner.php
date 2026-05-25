<?php

declare(strict_types=1);

$app = require __DIR__ . '/../bootstrap/app.php';
$config = $app['config'] ?? [];
$paths = $config['paths'] ?? [];

$jobRepository = new \Ailhost\Repositories\JsonJobRepository((string) ($paths['local_var_path'] ?? __DIR__ . '/../var'));
$jobService = new \Ailhost\Services\JobService($jobRepository);
$installService = new \Ailhost\Services\InstallService($paths, $jobService);
$siteService = new \Ailhost\Services\SiteService($paths, $installService, $jobService);
$nginxAdapter = new \Ailhost\Adapters\NginxAdapter(AILHOST_ROOT);
$dnsAdapter = new \Ailhost\Adapters\DnsAdapter(AILHOST_ROOT);
$dockerAdapter = new \Ailhost\Adapters\DockerAdapter(AILHOST_ROOT);
$sslAdapter = new \Ailhost\Adapters\SslAdapter(AILHOST_ROOT);
$phpAdapter = new \Ailhost\Adapters\PhpAdapter(AILHOST_ROOT);
$mariaDbAdapter = new \Ailhost\Adapters\MariaDbAdapter(AILHOST_ROOT);
$wordpressAdapter = new \Ailhost\Adapters\WordPressAdapter(AILHOST_ROOT);
$backupAdapter = new \Ailhost\Adapters\BackupAdapter(AILHOST_ROOT);
$processSupervisorAdapter = new \Ailhost\Adapters\ProcessSupervisorAdapter(AILHOST_ROOT);
$packageAdapter = new \Ailhost\Adapters\PackageAdapter(AILHOST_ROOT);
$serviceOpsService = new \Ailhost\Services\ServiceOpsService($jobService, $nginxAdapter, $phpAdapter, $mariaDbAdapter, $dnsAdapter, $dockerAdapter);
$worker = new \Ailhost\Workers\JobWorker($jobService, $siteService, $nginxAdapter, $dnsAdapter, $sslAdapter, $phpAdapter, $mariaDbAdapter, $wordpressAdapter, $backupAdapter, $processSupervisorAdapter, $packageAdapter, $serviceOpsService);

$drain = in_array('--drain', $argv, true);
$processed = 0;

do {
    $pendingBefore = count(array_filter(
        $jobService->all(),
        static fn(array $job): bool => (string) ($job['status'] ?? '') === 'pending'
    ));
    if ($pendingBefore === 0) {
        break;
    }

    $worker->run();
    $processed++;

    $pendingAfter = count(array_filter(
        $jobService->all(),
        static fn(array $job): bool => (string) ($job['status'] ?? '') === 'pending'
    ));
    if ($pendingAfter >= $pendingBefore && !$drain) {
        break;
    }
} while ($drain && $processed < 50);

echo "ok processed={$processed}\n";
