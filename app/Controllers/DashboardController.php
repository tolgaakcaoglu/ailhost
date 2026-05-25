<?php

declare(strict_types=1);

namespace Ailhost\Controllers;

use Ailhost\Core\Request;
use Ailhost\Core\Response;
use Ailhost\Core\View;
use Ailhost\Services\AuthService;
use Ailhost\Services\AuditLogService;
use Ailhost\Services\CsrfService;
use Ailhost\Services\FlashService;
use Ailhost\Services\JobService;
use Ailhost\Services\ServiceOpsService;
use Ailhost\Services\SiteService;
use Ailhost\Services\SystemMetricsService;

final class DashboardController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly SiteService $siteService,
        private readonly JobService $jobService,
        private readonly ServiceOpsService $serviceOpsService,
        private readonly SystemMetricsService $systemMetricsService,
        private readonly AuditLogService $auditLogService,
        private readonly CsrfService $csrfService,
        private readonly FlashService $flashService
    )
    {
    }

    public function index(Request $request): Response
    {
        if (!$this->authService->isAuthenticated()) {
            $this->flashService->setToast('Devam etmek için giriş yapın.', 'error');
            return Response::redirect('/login');
        }

        $toast = $this->flashService->consumeToast();
        $sites = $this->siteService->allSites();
        $jobs = $this->jobService->all();
        $systemMetrics = $this->systemMetricsService->snapshot();
        $activeSites = array_values(array_filter($sites, static fn(array $s): bool => ($s['status'] ?? '') === 'active'));
        $latestBackup = 'yok';
        foreach (array_reverse($jobs) as $job) {
            if (($job['type'] ?? '') === 'backup_create') {
                $latestBackup = (string) ($job['finished_at'] ?? $job['created_at'] ?? 'yok');
                break;
            }
        }
        $jobStatusCounts = [
            'pending' => 0,
            'running' => 0,
            'done' => 0,
            'failed' => 0,
        ];
        foreach ($jobs as $job) {
            $status = (string) ($job['status'] ?? '');
            if (isset($jobStatusCounts[$status])) {
                $jobStatusCounts[$status]++;
            }
        }
        $primarySite = $activeSites[0] ?? ($sites[0] ?? []);

        return new Response(View::render('dashboard/index', [
            'title' => 'Başlangıç Merkezi',
            'totalSites' => count($sites),
            'activeSites' => count($activeSites),
            'latestBackup' => $latestBackup,
            'serverIp' => (string) ((require AILHOST_ROOT . '/config/app.php')['app_url'] ?? ''),
            'systemMetrics' => $systemMetrics,
            'serviceHealthRows' => $this->serviceOpsService->health(),
            'appSidebarIdentity' => is_array($systemMetrics['identity'] ?? null) ? $systemMetrics['identity'] : [],
            'primarySiteId' => (string) ($primarySite['id'] ?? ''),
            'primarySiteDomain' => (string) ($primarySite['domain'] ?? ''),
            'jobStatusCounts' => $jobStatusCounts,
            'recentActivities' => $this->auditLogService->recent(8),
            'topbarJobSummary' => [
                'pending' => (int) ($jobStatusCounts['pending'] ?? 0),
                'running' => (int) ($jobStatusCounts['running'] ?? 0),
                'failed' => (int) ($jobStatusCounts['failed'] ?? 0),
            ],
            'authEmail' => $this->authService->currentEmail(),
            'authRole' => $this->authService->currentRole(),
            'permissions' => [
                'deploy_run' => $this->authService->can('deploy.run'),
                'backup_restore' => $this->authService->can('backup.restore'),
                'runtime_control' => $this->authService->can('runtime.control'),
                'dns_delete' => $this->authService->can('dns.delete'),
            ],
            'csrfToken' => $this->csrfService->token(),
            'toastMessage' => (string) ($toast['message'] ?? ''),
            'toastType' => (string) ($toast['type'] ?? 'info'),
            'toastPersistent' => (bool) ($toast['persistent'] ?? false),
        ]));
    }

    public function metrics(Request $request): Response
    {
        if (!$this->authService->isAuthenticated()) {
            return new Response(
                (string) json_encode(['ok' => false, 'message' => 'Oturum gerekli.'], JSON_UNESCAPED_UNICODE),
                401,
                ['Content-Type' => 'application/json; charset=UTF-8']
            );
        }

        $snapshot = $this->systemMetricsService->snapshot();
        $metrics = [
            'cpu' => $snapshot['cpu'] ?? [],
            'memory' => $snapshot['memory'] ?? [],
            'disk' => $snapshot['disk'] ?? [],
        ];

        return new Response(
            (string) json_encode(['ok' => true, 'metrics' => $metrics], JSON_UNESCAPED_UNICODE),
            200,
            [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Cache-Control' => 'no-store',
            ]
        );
    }
}
