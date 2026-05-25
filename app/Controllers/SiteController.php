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
use Ailhost\Services\FileManagerService;
use Ailhost\Services\JobService;
use Ailhost\Services\MailService;
use Ailhost\Services\SecurityService;
use Ailhost\Services\ServiceHealthService;
use Ailhost\Services\SiteService;

final class SiteController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly SiteService $siteService,
        private readonly JobService $jobService,
        private readonly FileManagerService $fileManagerService,
        private readonly MailService $mailService,
        private readonly SecurityService $securityService,
        private readonly ServiceHealthService $serviceHealthService,
        private readonly AuditLogService $auditLogService,
        private readonly CsrfService $csrfService,
        private readonly FlashService $flashService
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->renderSites($request, 'list');
    }

    public function overview(Request $request): Response
    {
        return $this->renderSites($request, 'overview');
    }

    public function domains(Request $request): Response
    {
        return $this->renderSites($request, 'domains');
    }

    public function runtime(Request $request): Response
    {
        return $this->renderSites($request, 'runtime');
    }

    public function proxy(Request $request): Response
    {
        return $this->renderSites($request, 'proxy');
    }

    public function deploy(Request $request): Response
    {
        return $this->renderSites($request, 'deploy');
    }

    public function files(Request $request): Response
    {
        return $this->renderSites($request, 'files');
    }

    public function mail(Request $request): Response
    {
        return $this->renderSites($request, 'mail');
    }

    public function dns(Request $request): Response
    {
        return $this->renderSites($request, 'dns');
    }

    public function backups(Request $request): Response
    {
        return $this->renderSites($request, 'backups');
    }

    public function security(Request $request): Response
    {
        return $this->renderSites($request, 'security');
    }

    public function ssl(Request $request): Response
    {
        return $this->renderSites($request, 'ssl');
    }

    public function createWizard(Request $request): Response
    {
        if (!$this->authService->isAuthenticated()) {
            $this->flashService->setToast('Devam etmek için giriş yapın.', 'error');
            return Response::redirect('/login');
        }

        $wizard = is_array($_SESSION['site_create_wizard'] ?? null) ? $_SESSION['site_create_wizard'] : [];
        $step = trim((string) $request->query('step', '1'));
        if (!in_array($step, ['1', '2', '3', '4'], true)) {
            $step = '1';
        }
        if ($step !== '1' && trim((string) ($wizard['domain'] ?? '')) === '') {
            $step = '1';
        }
        if (in_array($step, ['3', '4'], true) && trim((string) ($wizard['runtime'] ?? '')) === '') {
            $step = '2';
        }
        if ($step === '4' && trim((string) ($wizard['document_root'] ?? '')) === '') {
            $step = '3';
        }

        $toast = $this->flashService->consumeToast();
        return new Response(View::render('sites/create-wizard', [
            'title' => 'Website Oluşturma',
            'layoutMode' => 'app',
            'navActive' => 'sites',
            'step' => $step,
            'wizard' => $wizard,
            'csrfToken' => $this->csrfService->token(),
            'topbarJobSummary' => $this->jobSummary(),
            'authEmail' => $this->authService->currentEmail(),
            'authRole' => $this->authService->currentRole(),
            'toastMessage' => (string) ($toast['message'] ?? ''),
            'toastType' => (string) ($toast['type'] ?? 'info'),
            'toastPersistent' => (bool) ($toast['persistent'] ?? false),
        ]));
    }

    private function renderSites(Request $request, string $activeModule): Response
    {
        if (!$this->authService->isAuthenticated()) {
            $this->flashService->setToast('Devam etmek için giriş yapın.', 'error');
            return Response::redirect('/login');
        }

        $selectedSiteId = (string) $request->query('site', '');
        $listSearch = trim((string) $request->query('q', ''));
        $listStatus = trim((string) $request->query('status', 'all'));
        $allSites = $this->siteService->allSites();
        $filteredSites = array_values(array_filter($allSites, static function (array $site) use ($listSearch, $listStatus): bool {
            $domain = (string) ($site['domain'] ?? '');
            $status = (string) ($site['status'] ?? '');
            if ($listSearch !== '' && stripos($domain, $listSearch) === false) {
                return false;
            }
            if ($listStatus !== 'all' && $listStatus !== '' && $status !== $listStatus) {
                return false;
            }
            return true;
        }));
        $selectedSite = $selectedSiteId !== '' ? $this->siteService->findSite($selectedSiteId) : null;
        $selectedDomains = $selectedSiteId !== '' ? $this->siteService->domainsBySite($selectedSiteId) : [];
        $selectedFilePath = (string) $request->query('path', '');
        $selectedFile = (string) $request->query('file', '');
        $fileListing = [];
        $fileContent = [];
        $phpProfile = [];
        $runtimeState = [];
        $databases = [];
        $proxyRoutes = [];
        $deployProfile = [];
        $jobLocks = $selectedSiteId !== '' ? $this->siteService->siteJobLocks($selectedSiteId) : [];
        $mailboxes = [];
        $mailDomainRecords = [];
        $mailQueue = [];
        $mailDeliveryLogs = [];
        $webmailSettings = [];
        $dnsRecords = [];
        $mailHealth = [];
        $securityStatus = [];
        $ftpAccounts = [];
        $securityChecklist = [];
        $wordpress = [];
        $wordpressCliPolicy = [];
        $wordpressSecurityReport = [];
        $backups = [];
        $backupSchedule = [];
        $runtimeLogs = [];
        $siteJobStatus = [];

        if ($selectedSiteId !== '') {
            $siteJobStatus = $this->siteJobStatus($selectedSiteId);
            if (in_array($activeModule, ['overview'], true)) {
                $databases = $this->siteService->databasesBySite($selectedSiteId);
                $mailboxes = $this->mailService->mailboxesBySite($selectedSiteId);
                $backups = $this->siteService->backupsBySite($selectedSiteId);
            }
            if (in_array($activeModule, ['runtime'], true)) {
                $phpProfile = $this->siteService->phpProfile($selectedSiteId);
                $runtimeState = $this->siteService->runtimeState($selectedSiteId);
                $databases = $this->siteService->databasesBySite($selectedSiteId);
                $deployProfile = $this->siteService->deployProfile($selectedSiteId);
                $wordpress = $this->siteService->wordpressData($selectedSiteId);
                $wordpressCliPolicy = $this->siteService->wordpressCliPolicy();
                $wordpressSecurityReport = $this->siteService->wordpressSecurityReport($selectedSiteId);
                $runtimeLogs = $this->runtimeLogs($selectedSiteId, 80);
            }
            if (in_array($activeModule, ['deploy'], true)) {
                $deployProfile = $this->siteService->deployProfile($selectedSiteId);
            }
            if (in_array($activeModule, ['files'], true)) {
                $fileListing = $this->fileManagerService->list($selectedSiteId, $selectedFilePath);
                $fileContent = $selectedFile !== '' ? $this->fileManagerService->readFile($selectedSiteId, $selectedFile) : [];
            }
            if (in_array($activeModule, ['mail'], true)) {
                $mailboxes = $this->mailService->mailboxesBySite($selectedSiteId);
                $mailDomainRecords = $this->mailService->mailDomainRecords($selectedSiteId);
                $mailQueue = $this->mailService->mailQueueBySite($selectedSiteId);
                $mailDeliveryLogs = $this->mailService->deliveryLogsBySite($selectedSiteId, 20);
                $webmailSettings = $this->mailService->webmailSettingsBySite($selectedSiteId);
                $mailHealth = $this->mailService->health();
            }
            if (in_array($activeModule, ['dns'], true)) {
                $dnsRecords = $this->siteService->dnsRecordsBySite($selectedSiteId);
            }
            if (in_array($activeModule, ['security'], true)) {
                $securityStatus = $this->securityService->securityStatus();
                $ftpAccounts = $this->securityService->ftpAccountsBySite($selectedSiteId);
                $securityChecklist = $this->securityService->securityChecklist($selectedSiteId);
            }
            if (in_array($activeModule, ['proxy'], true)) {
                $proxyRoutes = $this->siteService->proxyRoutesBySite($selectedSiteId);
            }
            if (in_array($activeModule, ['backups'], true)) {
                $backups = $this->siteService->backupsBySite($selectedSiteId);
                $backupSchedule = $this->siteService->backupSchedule($selectedSiteId);
            }
        }
        $siteActivities = $selectedSiteId !== '' ? $this->siteActivities($selectedSiteId, 8) : [];
        $sslStatuses = [];
        foreach ($selectedDomains as $domainRow) {
            $domain = (string) ($domainRow['domain'] ?? '');
            $file = AILHOST_ROOT . '/var/generated/ssl/' . $domain . '.json';
            if (!is_file($file)) {
                $sslStatuses[$domain] = 'bekleniyor';
                continue;
            }
            $decoded = json_decode((string) file_get_contents($file), true);
            $sslStatuses[$domain] = is_array($decoded) ? (string) ($decoded['status'] ?? 'bekleniyor') : 'bekleniyor';
        }
        $toast = $this->flashService->consumeToast();

        return new Response(View::render('sites/index', [
            'title' => 'Website Yönetimi',
            'activeModule' => $activeModule,
            'sites' => $allSites,
            'filteredSites' => $filteredSites,
            'listSearch' => $listSearch,
            'listStatus' => $listStatus,
            'domains' => $this->siteService->allDomains(),
            'selectedSite' => $selectedSite,
            'selectedDomains' => $selectedDomains,
            'phpProfile' => $phpProfile,
            'runtimeState' => $runtimeState,
            'databases' => $databases,
            'proxyRoutes' => $proxyRoutes,
            'deployProfile' => $deployProfile,
            'jobLocks' => $jobLocks,
            'mailboxes' => $mailboxes,
            'mailDomainRecords' => $mailDomainRecords,
            'mailQueue' => $mailQueue,
            'mailDeliveryLogs' => $mailDeliveryLogs,
            'webmailSettings' => $webmailSettings,
            'dnsRecords' => $dnsRecords,
            'mailHealth' => $mailHealth,
            'fileListing' => $fileListing,
            'fileUploadMaxMb' => $this->fileManagerService->maxUploadMb(),
            'selectedFilePath' => $selectedFilePath,
            'selectedFile' => $selectedFile,
            'fileContent' => $fileContent,
            'securityStatus' => $securityStatus,
            'ftpAccounts' => $ftpAccounts,
            'securityChecklist' => $securityChecklist,
            'wordpress' => $wordpress,
            'wordpressCliPolicy' => $wordpressCliPolicy,
            'wordpressSecurityReport' => $wordpressSecurityReport,
            'backups' => $backups,
            'backupSchedule' => $backupSchedule,
            'runtimeLogs' => $runtimeLogs,
            'siteJobStatus' => $siteJobStatus,
            'siteActivities' => $siteActivities,
            'sslStatuses' => $sslStatuses,
            'serviceHealth' => $this->serviceHealthService->status(),
            'topbarJobSummary' => $this->jobSummary(),
            'authEmail' => $this->authService->currentEmail(),
            'authRole' => $this->authService->currentRole(),
            'permissions' => [
                'site_delete' => $this->authService->can('site.delete'),
                'backup_restore' => $this->authService->can('backup.restore'),
                'dns_delete' => $this->authService->can('dns.delete'),
                'deploy_run' => $this->authService->can('deploy.run'),
                'runtime_control' => $this->authService->can('runtime.control'),
            ],
            'csrfToken' => $this->csrfService->token(),
            'toastMessage' => (string) ($toast['message'] ?? ''),
            'toastType' => (string) ($toast['type'] ?? 'info'),
            'toastPersistent' => (bool) ($toast['persistent'] ?? false),
        ]));
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function runtimeLogs(string $siteId, int $maxLines = 80): array
    {
        $jobs = $this->readJsonFile(AILHOST_ROOT . '/var/jobs.json');
        $jobLogs = $this->readJsonFile(AILHOST_ROOT . '/var/job_logs.json');
        if (!is_array($jobs) || !is_array($jobLogs)) {
            return [];
        }

        $runtimeJobTypes = [
            'site_php_update',
            'runtime_start',
            'runtime_stop',
            'runtime_restart',
            'db_create',
            'db_delete',
            'db_user_reset',
            'wp_install',
            'wp_plugin_install',
            'wp_plugin_delete',
            'wp_theme_activate',
            'wp_core_update',
            'wp_maintenance_toggle',
            'wp_staging_create',
            'wp_staging_sync_live',
        ];
        $runtimeJobIds = [];
        foreach ($jobs as $job) {
            if (!is_array($job)) {
                continue;
            }
            $jobType = (string) ($job['type'] ?? '');
            $payload = is_array($job['payload'] ?? null) ? $job['payload'] : [];
            if (($payload['site_id'] ?? '') === $siteId && in_array($jobType, $runtimeJobTypes, true)) {
                $runtimeJobIds[(string) ($job['id'] ?? '')] = true;
            }
        }
        if ($runtimeJobIds === []) {
            return [];
        }

        $lines = [];
        foreach ($jobLogs as $log) {
            if (!is_array($log)) {
                continue;
            }
            $jobId = (string) ($log['job_id'] ?? '');
            if (!isset($runtimeJobIds[$jobId])) {
                continue;
            }
            $lines[] = [
                'time' => (string) ($log['time'] ?? ''),
                'stream' => (string) ($log['stream'] ?? 'info'),
                'message' => (string) ($log['message'] ?? ''),
            ];
        }
        if ($lines === []) {
            return [];
        }
        return array_slice($lines, -$maxLines);
    }

    private function readJsonFile(string $file): mixed
    {
        if (!is_file($file)) {
            return [];
        }
        return json_decode((string) file_get_contents($file), true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function siteActivities(string $siteId, int $limit = 8): array
    {
        $all = $this->auditLogService->recent(300);
        $filtered = array_values(array_filter($all, static function (array $row) use ($siteId): bool {
            $resourceId = (string) ($row['resource_id'] ?? '');
            $metadata = is_array($row['metadata'] ?? null) ? $row['metadata'] : [];
            $metaSiteId = (string) ($metadata['site_id'] ?? '');
            return $resourceId === $siteId || $metaSiteId === $siteId;
        }));
        return array_slice(array_reverse($filtered), 0, $limit);
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

    /**
     * @return array<int, array<string, string>>
     */
    private function siteJobStatus(string $siteId): array
    {
        $groups = [
            'deploy' => ['deploy_run'],
            'runtime' => ['runtime_start', 'runtime_stop', 'runtime_restart'],
            'backup_restore' => ['backup_create', 'backup_restore', 'backup_restore_dry_run', 'backup_retention_cleanup'],
            'dns_proxy' => ['dns_apply', 'proxy_apply'],
            'wordpress' => ['wp_install', 'wp_plugin_install', 'wp_plugin_delete', 'wp_theme_activate', 'wp_core_update', 'wp_maintenance_toggle', 'wp_staging_create', 'wp_staging_sync_live'],
        ];
        $labels = [
            'deploy' => 'Deploy',
            'runtime' => 'Runtime',
            'backup_restore' => 'Backup / Restore',
            'dns_proxy' => 'DNS / Proxy',
            'wordpress' => 'WordPress',
        ];

        $jobs = array_reverse($this->jobService->all());
        $rows = [];
        foreach ($groups as $group => $types) {
            $matched = null;
            foreach ($jobs as $job) {
                if (!is_array($job)) {
                    continue;
                }
                $type = (string) ($job['type'] ?? '');
                if (!in_array($type, $types, true)) {
                    continue;
                }
                $payload = is_array($job['payload'] ?? null) ? $job['payload'] : [];
                if ((string) ($payload['site_id'] ?? '') !== $siteId) {
                    continue;
                }
                $matched = $job;
                break;
            }

            $status = 'Beklenmiyor';
            $detail = '-';
            if (is_array($matched)) {
                $jobStatus = (string) ($matched['status'] ?? '');
                $status = match ($jobStatus) {
                    'pending' => 'Bekliyor',
                    'running' => 'Çalışıyor',
                    'done' => 'Tamamlandı',
                    'failed' => 'Hata',
                    default => 'Beklenmiyor',
                };
                $error = trim((string) ($matched['error'] ?? ''));
                $detail = $jobStatus === 'failed' && $error !== ''
                    ? $error
                    : (string) ($matched['finished_at'] ?? $matched['started_at'] ?? $matched['created_at'] ?? '-');
            }

            $rows[] = [
                'group' => $group,
                'label' => (string) ($labels[$group] ?? $group),
                'status' => $status,
                'detail' => $detail,
            ];
        }

        return $rows;
    }

    public function create(Request $request): Response
    {
        if (($guard = $this->guardWrite($request, '/sites')) !== null) {
            return $guard;
        }

        $domain = (string) $request->input('domain', '');
        $result = $this->siteService->createSite($domain);
        $ok = (bool) ($result['ok'] ?? false);

        $this->auditLogService->log(
            $this->authService->currentEmail(),
            $ok ? 'site.create' : 'site.create_failed',
            'site',
            $ok ? (string) ($result['site_id'] ?? 'unknown') : trim($domain),
            ['domain' => trim($domain)]
        );

        $this->flashService->setToast((string) ($result['message'] ?? ''), $ok ? 'info' : 'error');
        return Response::redirect($ok ? '/sites/overview?site=' . urlencode((string) ($result['site_id'] ?? '')) : '/sites');
    }

    public function createWizardSubmit(Request $request): Response
    {
        if (($guard = $this->guardWrite($request, '/sites/create-wizard')) !== null) {
            return $guard;
        }

        $wizard = is_array($_SESSION['site_create_wizard'] ?? null) ? $_SESSION['site_create_wizard'] : [];
        $step = (string) $request->input('step', '1');

        if ($step === '1') {
            $domain = mb_strtolower(trim((string) $request->input('domain', '')));
            if ($domain === '') {
                $this->flashService->setToast('Domain zorunlu.', 'error');
                return Response::redirect('/sites/create-wizard?step=1');
            }
            $wizard['domain'] = $domain;
            $_SESSION['site_create_wizard'] = $wizard;
            return Response::redirect('/sites/create-wizard?step=2');
        }

        if ($step === '2') {
            $runtime = trim((string) $request->input('runtime', 'php'));
            $webServer = trim((string) $request->input('web_server', 'nginx'));
            if (!in_array($runtime, ['php', 'node'], true) || !in_array($webServer, ['nginx', 'openlitespeed'], true)) {
                $this->flashService->setToast('Geçersiz runtime veya web sunucusu seçimi.', 'error');
                return Response::redirect('/sites/create-wizard?step=2');
            }
            $wizard['runtime'] = $runtime;
            $wizard['web_server'] = $webServer;
            $_SESSION['site_create_wizard'] = $wizard;
            return Response::redirect('/sites/create-wizard?step=3');
        }

        if ($step === '3') {
            $documentRoot = trim((string) $request->input('document_root', ''));
            $provisionDns = ((string) $request->input('provision_dns', '1')) === '1';
            $provisionSsl = ((string) $request->input('provision_ssl', '1')) === '1';
            if ($documentRoot === '') {
                $this->flashService->setToast('Document root zorunlu.', 'error');
                return Response::redirect('/sites/create-wizard?step=3');
            }
            $wizard['document_root'] = $documentRoot;
            $wizard['provision_dns'] = $provisionDns;
            $wizard['provision_ssl'] = $provisionSsl;
            $_SESSION['site_create_wizard'] = $wizard;
            return Response::redirect('/sites/create-wizard?step=4');
        }

        if ($step !== '4') {
            return Response::redirect('/sites/create-wizard?step=1');
        }

        $result = $this->siteService->createSiteFromWizard($wizard);
        $ok = (bool) ($result['ok'] ?? false);
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            $ok ? 'site.create' : 'site.create_failed',
            'site',
            $ok ? (string) ($result['site_id'] ?? 'unknown') : trim((string) ($wizard['domain'] ?? '')),
            $wizard
        );
        if ($ok) {
            unset($_SESSION['site_create_wizard']);
        }
        $this->flashService->setToast((string) ($result['message'] ?? ''), $ok ? 'info' : 'error');
        return Response::redirect($ok ? '/sites/overview?site=' . urlencode((string) ($result['site_id'] ?? '')) : '/sites/create-wizard?step=4');
    }

    public function addDomain(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/domains', $siteId)) !== null) {
            return $guard;
        }
        $domain = (string) $request->input('domain', '');
        $result = $this->siteService->addDomain($siteId, $domain, 'alias');
        $ok = (bool) ($result['ok'] ?? false);

        $this->auditLogService->log(
            $this->authService->currentEmail(),
            $ok ? 'domain.add' : 'domain.add_failed',
            'domain',
            trim($domain),
            ['site_id' => $siteId, 'domain' => trim($domain)]
        );

        $this->flashService->setToast((string) ($result['message'] ?? ''), $ok ? 'info' : 'error');
        return Response::redirect('/sites/domains?site=' . urlencode($siteId));
    }

    public function sslAction(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/ssl', $siteId)) !== null) {
            return $guard;
        }
        $domain = (string) $request->input('domain', '');
        $actionType = (string) $request->input('action_type', 'issue');
        $result = $this->siteService->requestSslAction($siteId, $domain, $actionType);
        $ok = (bool) ($result['ok'] ?? false);
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            $ok ? 'ssl.action' : 'ssl.action_failed',
            'ssl',
            $domain,
            ['site_id' => $siteId, 'action' => $actionType]
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), $ok ? 'info' : 'error');
        return Response::redirect('/sites/ssl?site=' . urlencode($siteId));
    }

    public function saveForceHttps(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/ssl', $siteId)) !== null) {
            return $guard;
        }
        $enabled = ((string) $request->input('force_https', '0')) === '1';
        $result = $this->siteService->saveForceHttps($siteId, $enabled);
        $ok = (bool) ($result['ok'] ?? false);
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            $ok ? 'ssl.force_https_update' : 'ssl.force_https_update_failed',
            'ssl',
            $siteId,
            ['enabled' => $enabled]
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), $ok ? 'info' : 'error');
        return Response::redirect('/sites/ssl?site=' . urlencode($siteId));
    }

    public function deleteDomain(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/domains', $siteId)) !== null) {
            return $guard;
        }
        $domainId = (string) $request->input('domain_id', '');
        $result = $this->siteService->removeDomain($domainId);
        $ok = (bool) ($result['ok'] ?? false);

        $this->auditLogService->log(
            $this->authService->currentEmail(),
            $ok ? 'domain.delete' : 'domain.delete_failed',
            'domain',
            $domainId,
            ['site_id' => $siteId]
        );

        $this->flashService->setToast((string) ($result['message'] ?? ''), $ok ? 'info' : 'error');
        return Response::redirect('/sites/domains?site=' . urlencode($siteId));
    }

    public function deleteSite(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites', $siteId, 'site.delete')) !== null) {
            return $guard;
        }
        $confirmationDomain = (string) $request->input('confirm_domain', '');
        $result = $this->siteService->requestSiteDelete($siteId, $confirmationDomain);
        $ok = (bool) ($result['ok'] ?? false);

        $this->auditLogService->log(
            $this->authService->currentEmail(),
            $ok ? 'site.delete' : 'site.delete_failed',
            'site',
            $siteId,
            ['confirm_domain' => trim($confirmationDomain)]
        );

        $this->flashService->setToast((string) ($result['message'] ?? ''), $ok ? 'info' : 'error');
        return Response::redirect('/sites');
    }

    public function savePhp(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/runtime', $siteId)) !== null) {
            return $guard;
        }
        $result = $this->siteService->savePhpProfile(
            $siteId,
            (string) $request->input('php_version', '8.3'),
            (string) $request->input('memory_limit', '256M'),
            (string) $request->input('upload_max_filesize', '64M'),
            (int) $request->input('max_execution_time', 120)
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites/overview?site=' . urlencode($siteId));
    }

    public function createDatabase(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/runtime', $siteId)) !== null) {
            return $guard;
        }
        $result = $this->siteService->createDatabase(
            $siteId,
            (string) $request->input('db_name', ''),
            (string) $request->input('db_user', ''),
            (string) $request->input('db_password', '')
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites/runtime?site=' . urlencode($siteId));
    }

    public function deleteDatabase(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/runtime', $siteId)) !== null) {
            return $guard;
        }
        $result = $this->siteService->deleteDatabase($siteId, (string) $request->input('db_id', ''));
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites/runtime?site=' . urlencode($siteId));
    }

    public function resetDatabaseUser(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/runtime', $siteId)) !== null) {
            return $guard;
        }
        $result = $this->siteService->resetDatabaseUserPassword(
            $siteId,
            (string) $request->input('db_id', ''),
            (string) $request->input('new_password', '')
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites/runtime?site=' . urlencode($siteId));
    }

    public function installWordPress(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/runtime', $siteId)) !== null) {
            return $guard;
        }
        $result = $this->siteService->requestWordPressInstall(
            $siteId,
            (string) $request->input('wp_admin_user', ''),
            (string) $request->input('wp_admin_password', ''),
            (string) $request->input('wp_admin_email', '')
        );
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            (bool) ($result['ok'] ?? false) ? 'wp.install' : 'wp.install_failed',
            'wordpress',
            $siteId
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites/runtime?site=' . urlencode($siteId));
    }

    public function wordpressPlugin(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/runtime', $siteId)) !== null) {
            return $guard;
        }
        $result = $this->siteService->requestWordPressPluginAction(
            $siteId,
            (string) $request->input('plugin', ''),
            (string) $request->input('action_type', 'install')
        );
        $actionType = (string) $request->input('action_type', 'install');
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            (bool) ($result['ok'] ?? false) ? 'wp.plugin.' . $actionType : 'wp.plugin.' . $actionType . '_failed',
            'wordpress',
            $siteId
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites/runtime?site=' . urlencode($siteId));
    }

    public function wordpressTheme(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/runtime', $siteId)) !== null) {
            return $guard;
        }
        $result = $this->siteService->requestWordPressThemeActivate($siteId, (string) $request->input('theme', ''));
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            (bool) ($result['ok'] ?? false) ? 'wp.theme.activate' : 'wp.theme.activate_failed',
            'wordpress',
            $siteId
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites/runtime?site=' . urlencode($siteId));
    }

    public function wordpressCoreUpdate(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/runtime', $siteId)) !== null) {
            return $guard;
        }
        $result = $this->siteService->requestWordPressCoreUpdate($siteId);
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            (bool) ($result['ok'] ?? false) ? 'wp.core.update' : 'wp.core.update_failed',
            'wordpress',
            $siteId
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites/runtime?site=' . urlencode($siteId));
    }

    public function wordpressMaintenance(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/backups', $siteId)) !== null) {
            return $guard;
        }
        $enabled = ((string) $request->input('enabled', '0')) === '1';
        $result = $this->siteService->requestWordPressMaintenanceToggle($siteId, $enabled);
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            (bool) ($result['ok'] ?? false) ? 'wp.maintenance.toggle' : 'wp.maintenance.toggle_failed',
            'wordpress',
            $siteId,
            ['enabled' => $enabled]
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites/backups?site=' . urlencode($siteId));
    }

    public function createWordPressStaging(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/runtime', $siteId)) !== null) {
            return $guard;
        }
        $result = $this->siteService->requestWordPressStagingCreate($siteId);
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            (bool) ($result['ok'] ?? false) ? 'wp.staging.create' : 'wp.staging.create_failed',
            'wordpress',
            $siteId
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites/runtime?site=' . urlencode($siteId));
    }

    public function syncWordPressStagingToLive(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/runtime', $siteId)) !== null) {
            return $guard;
        }
        $confirmDomain = (string) $request->input('confirm_domain', '');
        $result = $this->siteService->requestWordPressStagingSyncToLive($siteId, $confirmDomain);
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            (bool) ($result['ok'] ?? false) ? 'wp.staging.sync_live' : 'wp.staging.sync_live_failed',
            'wordpress',
            $siteId
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites/runtime?site=' . urlencode($siteId));
    }

    public function scanWordPressSecurity(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/runtime', $siteId)) !== null) {
            return $guard;
        }
        $result = $this->siteService->refreshWordPressSecuritySignals($siteId);
        $report = is_array($result['report'] ?? null) ? $result['report'] : [];
        $critical = (int) ($report['critical_count'] ?? 0);
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            ($result['ok'] ?? false) ? ($critical > 0 ? 'wp.security.scan_critical' : 'wp.security.scan_ok') : 'wp.security.scan_failed',
            'wordpress',
            $siteId,
            ['critical_count' => $critical, 'score' => (int) ($report['score'] ?? 0)]
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites/runtime?site=' . urlencode($siteId));
    }

    public function createBackup(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/backups', $siteId)) !== null) {
            return $guard;
        }
        $result = $this->siteService->requestBackup($siteId, (string) $request->input('backup_type', 'full'));
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites/backups?site=' . urlencode($siteId));
    }

    public function restoreBackup(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/backups', $siteId, 'backup.restore')) !== null) {
            return $guard;
        }
        $backupId = (string) $request->input('backup_id', '');
        $result = $this->siteService->requestRestore($siteId, $backupId);
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites/backups?site=' . urlencode($siteId));
    }

    public function dryRunRestore(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/backups', $siteId, 'backup.restore')) !== null) {
            return $guard;
        }
        $backupId = (string) $request->input('backup_id', '');
        $result = $this->siteService->requestRestoreDryRun($siteId, $backupId);
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites/backups?site=' . urlencode($siteId));
    }

    public function cleanupBackups(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/backups', $siteId, 'backup.restore')) !== null) {
            return $guard;
        }
        $keepLast = (int) $request->input('keep_last', 5);
        $result = $this->siteService->requestRetentionCleanup($siteId, max(1, min(20, $keepLast)));
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites/backups?site=' . urlencode($siteId));
    }

    public function saveBackupSchedule(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/backups', $siteId)) !== null) {
            return $guard;
        }
        $enabled = ((string) $request->input('enabled', '0')) === '1';
        $frequency = (string) $request->input('frequency', 'daily');
        $hour = (int) $request->input('hour', 2);
        $keepLast = (int) $request->input('keep_last', 5);
        $result = $this->siteService->saveBackupSchedule($siteId, $enabled, $frequency, $hour, $keepLast);
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites?site=' . urlencode($siteId));
    }

    public function saveFile(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/files', $siteId)) !== null) {
            return $guard;
        }
        $path = (string) $request->input('path', '');
        $content = (string) $request->input('content', '');
        $result = $this->fileManagerService->saveFile($siteId, $path, $content);
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        $parentPath = dirname($path);
        if ($parentPath === '.') {
            $parentPath = '';
        }
        return Response::redirect('/sites/files?site=' . urlencode($siteId) . '&path=' . urlencode($parentPath) . '&file=' . urlencode($path));
    }

    public function uploadFile(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/files', $siteId)) !== null) {
            return $guard;
        }
        $currentPath = (string) $request->input('current_path', '');
        $file = is_array($_FILES['upload_file'] ?? null) ? $_FILES['upload_file'] : [];
        $result = $this->fileManagerService->uploadFile($siteId, $currentPath, $file);
        $ok = (bool) ($result['ok'] ?? false);
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            $ok ? 'file.upload' : 'file.upload_failed',
            'file',
            (string) ($result['path'] ?? $currentPath)
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), $ok ? 'info' : 'error');
        return Response::redirect('/sites/files?site=' . urlencode($siteId) . '&path=' . urlencode($currentPath));
    }

    public function downloadFile(Request $request): Response
    {
        if (!$this->authService->isAuthenticated()) {
            return Response::redirect('/login');
        }
        $siteId = (string) $request->query('site', '');
        $path = (string) $request->query('path', '');
        $result = $this->fileManagerService->downloadFile($siteId, $path);
        if (($result['ok'] ?? false) !== true) {
            $this->flashService->setToast((string) ($result['message'] ?? 'Dosya indirilemedi.'), 'error');
            return Response::redirect('/sites/files?site=' . urlencode($siteId));
        }
        $filename = (string) ($result['filename'] ?? 'download.bin');
        $safeFilename = str_replace('"', '', $filename);
        return new Response(
            (string) ($result['content'] ?? ''),
            200,
            [
                'Content-Type' => (string) ($result['mime'] ?? 'application/octet-stream'),
                'Content-Disposition' => 'attachment; filename="' . $safeFilename . '"',
            ]
        );
    }

    public function deleteFile(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/files', $siteId)) !== null) {
            return $guard;
        }
        $path = (string) $request->input('path', '');
        $result = $this->fileManagerService->deletePath($siteId, $path);
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites/files?site=' . urlencode($siteId));
    }

    public function moveFile(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/files', $siteId)) !== null) {
            return $guard;
        }
        $sourcePath = (string) $request->input('source_path', '');
        $destinationPath = (string) $request->input('destination_path', '');
        $result = $this->fileManagerService->movePath($siteId, $sourcePath, $destinationPath);
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            (bool) ($result['ok'] ?? false) ? 'file.move' : 'file.move_failed',
            'file',
            $sourcePath,
            ['to' => $destinationPath]
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites/files?site=' . urlencode($siteId));
    }

    public function copyFile(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/files', $siteId)) !== null) {
            return $guard;
        }
        $sourcePath = (string) $request->input('source_path', '');
        $destinationPath = (string) $request->input('destination_path', '');
        $result = $this->fileManagerService->copyPath($siteId, $sourcePath, $destinationPath);
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            (bool) ($result['ok'] ?? false) ? 'file.copy' : 'file.copy_failed',
            'file',
            $sourcePath,
            ['to' => $destinationPath]
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites/files?site=' . urlencode($siteId));
    }

    public function bulkDeleteFiles(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/files', $siteId)) !== null) {
            return $guard;
        }
        $rawPaths = $request->input('paths', []);
        $paths = is_array($rawPaths) ? array_values(array_filter(array_map('strval', $rawPaths), static fn(string $v): bool => trim($v) !== '')) : [];
        if ($paths === []) {
            $this->flashService->setToast('Toplu silme için en az bir öğe seçin.', 'error');
            return Response::redirect('/sites/files?site=' . urlencode($siteId));
        }
        $result = $this->fileManagerService->bulkDelete($siteId, $paths);
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            (bool) ($result['ok'] ?? false) ? 'file.bulk_delete' : 'file.bulk_delete_failed',
            'file',
            $siteId,
            ['count' => count($paths)]
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites/files?site=' . urlencode($siteId));
    }

    public function createFtpAccount(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/security', $siteId)) !== null) {
            return $guard;
        }
        $result = $this->securityService->createFtpAccount(
            $siteId,
            (string) $request->input('ftp_user', ''),
            (string) $request->input('ftp_password', '')
        );
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            (bool) ($result['ok'] ?? false) ? 'ftp.account_create' : 'ftp.account_create_failed',
            'security',
            $siteId
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites/security?site=' . urlencode($siteId));
    }

    public function resetFtpAccountPassword(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/security', $siteId)) !== null) {
            return $guard;
        }
        $accountId = (string) $request->input('ftp_account_id', '');
        $password = (string) $request->input('ftp_password', '');
        $result = $this->securityService->resetFtpPassword($siteId, $accountId, $password);
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            (bool) ($result['ok'] ?? false) ? 'ftp.password_reset' : 'ftp.password_reset_failed',
            'security',
            $accountId,
            ['site_id' => $siteId]
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites/security?site=' . urlencode($siteId));
    }

    public function setFtpAccountStatus(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/security', $siteId)) !== null) {
            return $guard;
        }
        $accountId = (string) $request->input('ftp_account_id', '');
        $disabled = ((string) $request->input('disabled', '1')) === '1';
        $result = $this->securityService->setFtpAccountDisabled($siteId, $accountId, $disabled);
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            (bool) ($result['ok'] ?? false) ? 'ftp.status_update' : 'ftp.status_update_failed',
            'security',
            $accountId,
            ['site_id' => $siteId, 'disabled' => $disabled]
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites/security?site=' . urlencode($siteId));
    }

    public function createDirectory(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/files', $siteId)) !== null) {
            return $guard;
        }
        $currentPath = (string) $request->input('current_path', '');
        $directoryName = (string) $request->input('directory_name', '');
        $result = $this->fileManagerService->createDirectory($siteId, $currentPath, $directoryName);
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites/files?site=' . urlencode($siteId) . '&path=' . urlencode($currentPath));
    }

    public function renamePath(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/files', $siteId)) !== null) {
            return $guard;
        }
        $sourcePath = (string) $request->input('source_path', '');
        $newName = (string) $request->input('new_name', '');
        $result = $this->fileManagerService->renamePath($siteId, $sourcePath, $newName);
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        $parent = dirname($sourcePath);
        if ($parent === '.') {
            $parent = '';
        }
        return Response::redirect('/sites/files?site=' . urlencode($siteId) . '&path=' . urlencode($parent));
    }

    public function createMailbox(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/mail', $siteId)) !== null) {
            return $guard;
        }
        $result = $this->mailService->createMailbox(
            $siteId,
            (string) $request->input('local_part', ''),
            (string) $request->input('mail_password', '')
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites/mail?site=' . urlencode($siteId));
    }

    public function applyMailDomainRecords(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/mail', $siteId)) !== null) {
            return $guard;
        }
        $dmarcPolicy = (string) $request->input('dmarc_policy', 'quarantine');
        $result = $this->mailService->applyMailDomainRecords($siteId, $dmarcPolicy);
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            (bool) ($result['ok'] ?? false) ? 'mail.domain_records.apply' : 'mail.domain_records.apply_failed',
            'mail',
            $siteId,
            ['dmarc_policy' => $dmarcPolicy]
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites/mail?site=' . urlencode($siteId));
    }

    public function deleteMailbox(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/mail', $siteId)) !== null) {
            return $guard;
        }
        $mailboxId = (string) $request->input('mailbox_id', '');
        $result = $this->mailService->deleteMailbox($siteId, $mailboxId);
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites/mail?site=' . urlencode($siteId));
    }

    public function retryMailQueueItem(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/mail', $siteId)) !== null) {
            return $guard;
        }
        $queueId = (string) $request->input('queue_id', '');
        $result = $this->mailService->retryQueueItem($siteId, $queueId);
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            (bool) ($result['ok'] ?? false) ? 'mail.queue.retry' : 'mail.queue.retry_failed',
            'mail',
            $queueId,
            ['site_id' => $siteId]
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites/mail?site=' . urlencode($siteId));
    }

    public function removeMailQueueItem(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/mail', $siteId)) !== null) {
            return $guard;
        }
        $queueId = (string) $request->input('queue_id', '');
        $result = $this->mailService->removeQueueItem($siteId, $queueId);
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            (bool) ($result['ok'] ?? false) ? 'mail.queue.remove' : 'mail.queue.remove_failed',
            'mail',
            $queueId,
            ['site_id' => $siteId]
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites/mail?site=' . urlencode($siteId));
    }

    public function saveWebmailSettings(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/mail', $siteId)) !== null) {
            return $guard;
        }
        $provider = (string) $request->input('provider', 'roundcube');
        $baseUrl = (string) $request->input('base_url', '');
        $installed = ((string) $request->input('installed', '0')) === '1';
        $result = $this->mailService->saveWebmailSettings($siteId, $provider, $baseUrl, $installed);
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            (bool) ($result['ok'] ?? false) ? 'mail.webmail.settings_save' : 'mail.webmail.settings_save_failed',
            'mail',
            $siteId,
            ['provider' => $provider, 'installed' => $installed]
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites/mail?site=' . urlencode($siteId));
    }

    public function addDnsRecord(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/dns', $siteId)) !== null) {
            return $guard;
        }
        $result = $this->siteService->addDnsRecord(
            $siteId,
            (string) $request->input('record_type', 'A'),
            (string) $request->input('record_name', ''),
            (string) $request->input('record_value', ''),
            (int) $request->input('record_ttl', 3600)
        );
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            (bool) ($result['ok'] ?? false) ? 'dns.record.add' : 'dns.record.add_failed',
            'dns',
            $siteId,
            [
                'type' => (string) $request->input('record_type', 'A'),
                'name' => (string) $request->input('record_name', ''),
            ]
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites/dns?site=' . urlencode($siteId));
    }

    public function deleteDnsRecord(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/dns', $siteId, 'dns.delete')) !== null) {
            return $guard;
        }
        $recordId = (string) $request->input('record_id', '');
        $result = $this->siteService->deleteDnsRecord($siteId, $recordId);
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            (bool) ($result['ok'] ?? false) ? 'dns.record.delete' : 'dns.record.delete_failed',
            'dns',
            $siteId,
            ['record_id' => $recordId]
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites/dns?site=' . urlencode($siteId));
    }

    public function addProxyRoute(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/proxy', $siteId)) !== null) {
            return $guard;
        }
        $result = $this->siteService->addProxyRoute(
            $siteId,
            (string) $request->input('prefix', ''),
            (int) $request->input('target_port', 0),
            (string) $request->input('description', '')
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites/proxy?site=' . urlencode($siteId));
    }

    public function deleteProxyRoute(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/proxy', $siteId)) !== null) {
            return $guard;
        }
        $routeId = (string) $request->input('route_id', '');
        $result = $this->siteService->deleteProxyRoute($siteId, $routeId);
        $this->flashService->setToast((string) ($result['message'] ?? ''), (bool) ($result['ok'] ?? false) ? 'info' : 'error');
        return Response::redirect('/sites/proxy?site=' . urlencode($siteId));
    }

    public function runtimeControl(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/runtime', $siteId, 'runtime.control')) !== null) {
            return $guard;
        }
        $action = (string) $request->input('action_type', '');
        $result = $this->siteService->requestRuntimeControl($siteId, $action);
        $ok = (bool) ($result['ok'] ?? false);
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            $ok ? 'runtime.' . $action : 'runtime.' . $action . '_failed',
            'site',
            $siteId
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), $ok ? 'info' : 'error');
        return Response::redirect('/sites/runtime?site=' . urlencode($siteId));
    }

    public function saveDeployProfile(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/deploy', $siteId)) !== null) {
            return $guard;
        }
        $result = $this->siteService->saveDeployProfile(
            $siteId,
            (string) $request->input('source_type', 'manual'),
            (string) $request->input('source_value', ''),
            (string) $request->input('build_command', ''),
            (string) $request->input('start_command', ''),
            (int) $request->input('port', 3000),
            (string) $request->input('webhook_branch', 'main'),
            (string) $request->input('webhook_secret', '')
        );
        $ok = (bool) ($result['ok'] ?? false);
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            $ok ? 'deploy.profile.save' : 'deploy.profile.save_failed',
            'site',
            $siteId
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), $ok ? 'info' : 'error');
        return Response::redirect('/sites/deploy?site=' . urlencode($siteId));
    }

    public function deployWebhook(Request $request): Response
    {
        if ($request->method() !== 'POST') {
            return Response::json(['ok' => false, 'message' => 'Method not allowed'], 405);
        }

        $siteId = trim((string) ($_GET['site'] ?? ''));
        if ($siteId === '') {
            return Response::json(['ok' => false, 'message' => 'site zorunlu'], 400);
        }

        $profile = $this->siteService->deployProfile($siteId);
        if ($profile === []) {
            return Response::json(['ok' => false, 'message' => 'Deploy profili bulunamadı'], 404);
        }

        $secret = $this->siteService->webhookSecretForSite($siteId);
        if ($secret === '') {
            return Response::json(['ok' => false, 'message' => 'Webhook secret tanımlı değil'], 403);
        }

        $rawBody = (string) file_get_contents('php://input');
        $sigHeader = (string) ($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '');
        if (!str_starts_with($sigHeader, 'sha256=')) {
            return Response::json(['ok' => false, 'message' => 'İmza eksik'], 401);
        }
        $incoming = substr($sigHeader, 7);
        $calculated = hash_hmac('sha256', $rawBody, $secret);
        if (!hash_equals($calculated, $incoming)) {
            return Response::json(['ok' => false, 'message' => 'İmza doğrulanamadı'], 401);
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return Response::json(['ok' => false, 'message' => 'Geçersiz payload'], 400);
        }
        $ref = (string) ($payload['ref'] ?? '');
        $branch = str_starts_with($ref, 'refs/heads/') ? substr($ref, strlen('refs/heads/')) : $ref;
        if ($branch === '') {
            $branch = 'main';
        }

        $deliveryId = (string) ($_SERVER['HTTP_X_GITHUB_DELIVERY'] ?? '');
        $result = $this->siteService->requestDeployRunFromWebhook($siteId, 'github', $branch, $deliveryId);
        $ok = (bool) ($result['ok'] ?? false);

        $this->auditLogService->log(
            'webhook',
            $ok ? 'deploy.webhook.accepted' : 'deploy.webhook.rejected',
            'deploy',
            $siteId,
            ['branch' => $branch, 'delivery_id' => $deliveryId]
        );

        if (!$ok) {
            return Response::json(['ok' => false, 'message' => (string) ($result['message'] ?? 'Webhook reddedildi')], 202);
        }
        return Response::json(['ok' => true, 'message' => 'Deploy kuyruğa alındı']);
    }

    public function runDeploy(Request $request): Response
    {
        $siteId = '';
        if (($guard = $this->guardSiteWrite($request, '/sites/deploy', $siteId, 'deploy.run')) !== null) {
            return $guard;
        }
        $result = $this->siteService->requestDeployRun($siteId);
        $ok = (bool) ($result['ok'] ?? false);
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            $ok ? 'deploy.run' : 'deploy.run_failed',
            'site',
            $siteId
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), $ok ? 'info' : 'error');
        return Response::redirect('/sites/deploy?site=' . urlencode($siteId));
    }

    private function denyUnless(string $permission, string $redirect): ?Response
    {
        if ($this->authService->can($permission)) {
            return null;
        }
        $this->flashService->setToast('Bu işlem için yetkiniz yok.', 'error');
        return Response::redirect($redirect);
    }

    private function guardSiteWrite(
        Request $request,
        string $redirectPath,
        ?string &$siteId,
        ?string $permission = null,
        string $siteIdField = 'site_id',
        bool $mustExist = true
    ): ?Response {
        if (!$this->authService->isAuthenticated()) {
            $this->flashService->setToast('Devam etmek için giriş yapın.', 'error');
            return Response::redirect('/login');
        }
        if ($permission !== null && ($deny = $this->denyUnless($permission, '/sites')) !== null) {
            return $deny;
        }
        if (!$this->csrfService->isValid((string) $request->input('_csrf', ''))) {
            $this->flashService->setToast('İstek doğrulaması başarısız. Sayfayı yenileyin.', 'error');
            return Response::redirect($redirectPath);
        }
        $siteId = trim((string) $request->input($siteIdField, ''));
        if ($mustExist && ($siteId === '' || $this->siteService->findSite($siteId) === null)) {
            $this->flashService->setToast('Website bulunamadı.', 'error');
            return Response::redirect('/sites');
        }
        return null;
    }

    private function guardWrite(Request $request, string $redirectPath): ?Response
    {
        if (!$this->authService->isAuthenticated()) {
            $this->flashService->setToast('Devam etmek için giriş yapın.', 'error');
            return Response::redirect('/login');
        }
        if (!$this->csrfService->isValid((string) $request->input('_csrf', ''))) {
            $this->flashService->setToast('İstek doğrulaması başarısız. Sayfayı yenileyin.', 'error');
            return Response::redirect($redirectPath);
        }
        return null;
    }
}
