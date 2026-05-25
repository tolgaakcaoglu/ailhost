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
use Ailhost\Services\SiteService;

final class ObservabilityController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly JobService $jobService,
        private readonly SiteService $siteService,
        private readonly AuditLogService $auditLogService,
        private readonly CsrfService $csrfService,
        private readonly FlashService $flashService
    ) {
    }

    public function logs(Request $request): Response
    {
        if (($guard = $this->guardAuthenticated()) !== null) {
            return $guard;
        }
        $toast = $this->flashService->consumeToast();
        $query = trim((string) ($request->query('q') ?? ''));
        $source = trim((string) ($request->query('source') ?? 'activity'));
        $resourceType = trim((string) ($request->query('resource_type') ?? 'all'));
        $siteFilter = trim((string) ($request->query('site') ?? 'all'));
        $limit = (int) ($request->query('limit') ?? 100);
        if (!in_array($limit, [100, 500, 1000], true)) {
            $limit = 100;
        }

        $activities = $this->filterActivities(
            array_reverse($this->auditLogService->recent($limit)),
            $resourceType,
            $siteFilter,
            $query
        );

        $sites = $this->siteService->allSites();
        $siteOptions = [];
        foreach ($sites as $site) {
            $siteId = (string) ($site['id'] ?? '');
            if ($siteId === '') {
                continue;
            }
            $siteOptions[] = ['id' => $siteId, 'domain' => (string) ($site['domain'] ?? $siteId)];
        }

        $jobLogs = array_reverse($this->jobService->logs());
        if ($query !== '') {
            $jobLogs = array_values(array_filter($jobLogs, static function (array $line) use ($query): bool {
                $haystack = strtolower((string) ($line['message'] ?? '') . ' ' . (string) ($line['job_id'] ?? '') . ' ' . (string) ($line['stream'] ?? ''));
                return str_contains($haystack, strtolower($query));
            }));
        }
        $jobLogs = array_slice($jobLogs, 0, $limit);

        return new Response(View::render('observability/logs', [
            'title' => 'Loglar',
            'activities' => $activities,
            'jobLogs' => $jobLogs,
            'siteOptions' => $siteOptions,
            'selectedSite' => $siteFilter,
            'selectedType' => $resourceType,
            'selectedLimit' => $limit,
            'searchQuery' => $query,
            'selectedSource' => in_array($source, ['activity', 'job'], true) ? $source : 'activity',
            'topbarJobSummary' => $this->jobSummary(),
            'authEmail' => $this->authService->currentEmail(),
            'authRole' => $this->authService->currentRole(),
            'csrfToken' => $this->csrfService->token(),
            'toastMessage' => (string) ($toast['message'] ?? ''),
            'toastType' => (string) ($toast['type'] ?? 'info'),
            'toastPersistent' => (bool) ($toast['persistent'] ?? false),
        ]));
    }

    public function pruneAudit(Request $request): Response
    {
        if (($guard = $this->guardManagePost($request)) !== null) {
            return $guard;
        }
        $days = (int) $request->input('days', 90);
        $result = $this->auditLogService->pruneOlderThanDays($days);
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            'audit.prune',
            'audit',
            'retention',
            ['days' => $days, 'removed' => (int) ($result['removed'] ?? 0), 'remaining' => (int) ($result['remaining'] ?? 0)]
        );
        $this->flashService->setToast('Audit temizliği tamamlandı. Silinen: ' . (string) ($result['removed'] ?? 0), 'info');
        return Response::redirect('/logs');
    }

    public function exportAudit(Request $request): Response
    {
        if (($guard = $this->guardAuthenticated()) !== null) {
            return $guard;
        }

        $format = trim((string) ($request->query('format') ?? 'json'));
        if (!in_array($format, ['json', 'csv'], true)) {
            $format = 'json';
        }
        $resourceType = trim((string) ($request->query('resource_type') ?? 'all'));
        $siteFilter = trim((string) ($request->query('site') ?? 'all'));
        $query = trim((string) ($request->query('q') ?? ''));

        $activities = $this->filterActivities(
            array_reverse($this->auditLogService->all()),
            $resourceType,
            $siteFilter,
            $query
        );

        $ts = date('Ymd_His');
        if ($format === 'csv') {
            $lines = ['id,created_at,actor_email,action,resource_type,resource_id,metadata_json'];
            foreach ($activities as $row) {
                $meta = json_encode($row['metadata'] ?? [], JSON_UNESCAPED_UNICODE);
                $csvRow = [
                    (string) ($row['id'] ?? ''),
                    (string) ($row['created_at'] ?? ''),
                    (string) ($row['actor_email'] ?? ''),
                    (string) ($row['action'] ?? ''),
                    (string) ($row['resource_type'] ?? ''),
                    (string) ($row['resource_id'] ?? ''),
                    (string) ($meta === false ? '{}' : $meta),
                ];
                $escaped = array_map(static fn(string $v): string => '"' . str_replace('"', '""', $v) . '"', $csvRow);
                $lines[] = implode(',', $escaped);
            }
            return new Response(
                implode("\n", $lines) . "\n",
                200,
                [
                    'Content-Type' => 'text/csv; charset=UTF-8',
                    'Content-Disposition' => 'attachment; filename="audit_logs_' . $ts . '.csv"',
                ]
            );
        }

        return new Response(
            (string) json_encode($activities, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            200,
            [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="audit_logs_' . $ts . '.json"',
            ]
        );
    }

    public function jobs(Request $request): Response
    {
        if (($guard = $this->guardAuthenticated()) !== null) {
            return $guard;
        }
        $toast = $this->flashService->consumeToast();

        $status = trim((string) ($request->query('status') ?? 'all'));
        $query = trim((string) ($request->query('q') ?? ''));
        $limit = (int) ($request->query('limit') ?? 100);
        if (!in_array($limit, [100, 500, 1000], true)) {
            $limit = 100;
        }

        $jobs = array_reverse($this->jobService->all());
        if ($status !== 'all') {
            $jobs = array_values(array_filter($jobs, static fn (array $job): bool => (string) ($job['status'] ?? '') === $status));
        }
        if ($query !== '') {
            $jobs = array_values(array_filter($jobs, static function (array $job) use ($query): bool {
                $haystack = strtolower((string) ($job['id'] ?? '') . ' ' . (string) ($job['type'] ?? '') . ' ' . (string) ($job['error'] ?? ''));
                return str_contains($haystack, strtolower($query));
            }));
        }
        $jobs = array_slice($jobs, 0, $limit);
        $activeCount = count(array_filter($jobs, static fn (array $job): bool => in_array((string) ($job['status'] ?? ''), ['pending', 'running'], true)));
        $failedCount = count(array_filter($jobs, static fn (array $job): bool => (string) ($job['status'] ?? '') === 'failed'));

        return new Response(View::render('observability/jobs', [
            'title' => 'İşler',
            'jobs' => $jobs,
            'jobLogs' => array_reverse($this->jobService->logs()),
            'selectedStatus' => $status,
            'selectedLimit' => $limit,
            'searchQuery' => $query,
            'activeCount' => $activeCount,
            'failedCount' => $failedCount,
            'topbarJobSummary' => $this->jobSummary(),
            'authEmail' => $this->authService->currentEmail(),
            'authRole' => $this->authService->currentRole(),
            'csrfToken' => $this->csrfService->token(),
            'toastMessage' => (string) ($toast['message'] ?? ''),
            'toastType' => (string) ($toast['type'] ?? 'info'),
            'toastPersistent' => (bool) ($toast['persistent'] ?? false),
        ]));
    }

    /**
     * @return array<string, int>
     */
    private function jobSummary(): array
    {
        $counts = ['pending' => 0, 'running' => 0, 'failed' => 0];
        foreach ($this->jobService->all() as $job) {
            $status = (string) ($job['status'] ?? '');
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
        }
        return $counts;
    }

    private function guardAuthenticated(): ?Response
    {
        if ($this->authService->isAuthenticated()) {
            return null;
        }
        $this->flashService->setToast('Devam etmek için giriş yapın.', 'error');
        return Response::redirect('/login');
    }

    private function guardManagePost(Request $request): ?Response
    {
        if (($guard = $this->guardAuthenticated()) !== null) {
            return $guard;
        }
        if (!$this->authService->can('users.manage')) {
            $this->flashService->setToast('Bu işlem için yetkiniz yok.', 'error');
            return Response::redirect('/logs');
        }
        if ($this->csrfService->isValid((string) $request->input('_csrf', ''))) {
            return null;
        }
        $this->flashService->setToast('İstek doğrulaması başarısız. Sayfayı yenileyin.', 'error');
        return Response::redirect('/logs');
    }

    /**
     * @param array<int, array<string, mixed>> $activities
     * @return array<int, array<string, mixed>>
     */
    private function filterActivities(array $activities, string $resourceType, string $siteFilter, string $query): array
    {
        return array_values(array_filter($activities, static function (array $activity) use ($resourceType, $siteFilter, $query): bool {
            $currentType = (string) ($activity['resource_type'] ?? '');
            $currentId = (string) ($activity['resource_id'] ?? '');
            $action = (string) ($activity['action'] ?? '');
            $actor = (string) ($activity['actor_email'] ?? '');

            if ($resourceType !== 'all' && $currentType !== $resourceType) {
                return false;
            }
            if ($siteFilter !== 'all' && $currentId !== $siteFilter) {
                return false;
            }
            if ($query !== '') {
                $haystack = strtolower($action . ' ' . $actor . ' ' . $currentType . ' ' . $currentId);
                if (!str_contains($haystack, strtolower($query))) {
                    return false;
                }
            }
            return true;
        }));
    }
}
