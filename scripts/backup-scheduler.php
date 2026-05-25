<?php

declare(strict_types=1);

$app = require __DIR__ . '/../bootstrap/app.php';
$config = $app['config'] ?? [];
$paths = $config['paths'] ?? [];

$jobRepository = new \Ailhost\Repositories\JsonJobRepository((string) ($paths['local_var_path'] ?? __DIR__ . '/../var'));
$jobService = new \Ailhost\Services\JobService($jobRepository);
$installService = new \Ailhost\Services\InstallService($paths, $jobService);
$siteService = new \Ailhost\Services\SiteService($paths, $installService, $jobService);

$now = new DateTimeImmutable('now');
$hour = (int) $now->format('G');
$weekday = (int) $now->format('N'); // 1-7
$triggered = 0;

foreach ($siteService->allBackupSchedules() as $schedule) {
    $siteId = (string) ($schedule['site_id'] ?? '');
    if ($siteId === '' || !($schedule['enabled'] ?? false)) {
        continue;
    }

    $frequency = (string) ($schedule['frequency'] ?? 'daily');
    $scheduleHour = (int) ($schedule['hour'] ?? 2);
    if ($scheduleHour !== $hour) {
        continue;
    }

    if ($frequency === 'weekly' && $weekday !== 1) {
        continue;
    }

    $lastRunAt = (string) ($schedule['last_run_at'] ?? '');
    if ($lastRunAt !== '') {
        $last = new DateTimeImmutable($lastRunAt);
        if ($last->format('Y-m-d') === $now->format('Y-m-d')) {
            continue;
        }
    }

    $result = $siteService->requestBackup($siteId, 'full');
    if (($result['ok'] ?? false) === true) {
        $siteService->markScheduleRun($siteId);
        $keepLast = (int) ($schedule['keep_last'] ?? 5);
        $siteService->requestRetentionCleanup($siteId, $keepLast);
        $triggered++;
    }
}

echo "triggered={$triggered}\n";
