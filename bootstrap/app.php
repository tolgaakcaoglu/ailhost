<?php

declare(strict_types=1);

define('AILHOST_ROOT', dirname(__DIR__));

require_once AILHOST_ROOT . '/app/Core/Autoloader.php';

\Ailhost\Core\Autoloader::register(AILHOST_ROOT . '/app');

$paths = require AILHOST_ROOT . '/config/paths.php';
$appConfig = require AILHOST_ROOT . '/config/app.php';
$dbConfig = require AILHOST_ROOT . '/config/database.php';

$router = new \Ailhost\Core\Router();
$jobRepository = new \Ailhost\Repositories\JsonJobRepository($paths['local_var_path']);
$jobService = new \Ailhost\Services\JobService($jobRepository);
$auditLogRepository = new \Ailhost\Repositories\JsonAuditLogRepository($paths['local_var_path']);
$auditLogService = new \Ailhost\Services\AuditLogService($auditLogRepository);
$phpAdapter = new \Ailhost\Adapters\PhpAdapter(AILHOST_ROOT);
$mariaDbAdapter = new \Ailhost\Adapters\MariaDbAdapter(AILHOST_ROOT);
$nginxAdapter = new \Ailhost\Adapters\NginxAdapter(AILHOST_ROOT);
$dnsAdapter = new \Ailhost\Adapters\DnsAdapter(AILHOST_ROOT);
$dockerAdapter = new \Ailhost\Adapters\DockerAdapter(AILHOST_ROOT);
$ftpAdapter = new \Ailhost\Adapters\FtpAdapter();
$firewallAdapter = new \Ailhost\Adapters\FirewallAdapter();
$fail2banAdapter = new \Ailhost\Adapters\Fail2banAdapter();
$mailAdapter = new \Ailhost\Adapters\MailAdapter();
$serviceHealthService = new \Ailhost\Services\ServiceHealthService($phpAdapter, $mariaDbAdapter);
$serviceOpsService = new \Ailhost\Services\ServiceOpsService($jobService, $nginxAdapter, $phpAdapter, $mariaDbAdapter, $dnsAdapter, $dockerAdapter);
$systemMetricsService = new \Ailhost\Services\SystemMetricsService($paths['local_var_path']);
$installService = new \Ailhost\Services\InstallService($paths, $jobService);
$siteService = new \Ailhost\Services\SiteService($paths, $installService, $jobService);
$fileManagerService = new \Ailhost\Services\FileManagerService($siteService, $paths);
$mailService = new \Ailhost\Services\MailService($siteService, $mailAdapter);
$securityService = new \Ailhost\Services\SecurityService($siteService, $firewallAdapter, $fail2banAdapter, $ftpAdapter);
$csrfService = new \Ailhost\Services\CsrfService();
$flashService = new \Ailhost\Services\FlashService();
$userService = new \Ailhost\Services\UserService($paths, $installService);
$rateLimiterService = new \Ailhost\Services\RateLimiterService($paths['local_var_path']);
$authService = new \Ailhost\Services\AuthService($installService, $userService, $rateLimiterService, $paths);
$installController = new \Ailhost\Controllers\InstallController($installService, $jobService, $authService, $auditLogService, $csrfService, $flashService);
$authController = new \Ailhost\Controllers\AuthController($authService, $auditLogService, $csrfService, $flashService);
$dashboardController = new \Ailhost\Controllers\DashboardController($authService, $siteService, $jobService, $serviceOpsService, $systemMetricsService, $auditLogService, $csrfService, $flashService);
$observabilityController = new \Ailhost\Controllers\ObservabilityController($authService, $jobService, $siteService, $auditLogService, $csrfService, $flashService);
$usersController = new \Ailhost\Controllers\UsersController($authService, $userService, $jobService, $auditLogService, $csrfService, $flashService);
$accountController = new \Ailhost\Controllers\AccountController($authService, $userService, $jobService, $auditLogService, $csrfService, $flashService);
$serviceController = new \Ailhost\Controllers\ServiceController($authService, $serviceOpsService, $jobService, $auditLogService, $csrfService, $flashService);
$siteController = new \Ailhost\Controllers\SiteController($authService, $siteService, $jobService, $fileManagerService, $mailService, $securityService, $serviceHealthService, $auditLogService, $csrfService, $flashService);

$router->get('/', [$installController, 'index']);
$router->get('/login', [$authController, 'showLogin']);
$router->post('/login', [$authController, 'login']);
$router->get('/forgot-password', [$authController, 'showForgotPassword']);
$router->post('/forgot-password', [$authController, 'forgotPassword']);
$router->post('/logout', [$authController, 'logout']);
$router->get('/install', [$installController, 'index']);
$router->post('/install/admin', [$installController, 'saveAdmin']);
$router->post('/install/settings', [$installController, 'saveSettings']);
$router->post('/install/services', [$installController, 'saveServices']);
$router->post('/install/retry-failed-job', [$installController, 'retryFailedJob']);
$router->post('/install/lock', [$installController, 'lock']);
$router->post('/webhooks/deploy', [$siteController, 'deployWebhook']);
$router->get('/api/jobs/latest', [$installController, 'latestJobStatus']);
$router->get('/api/install/snapshot', [$installController, 'installSnapshot']);
$router->get('/api/install/dir-suggestions', [$installController, 'directorySuggestions']);
$router->get('/dashboard', [$dashboardController, 'index']);
$router->get('/api/dashboard/metrics', [$dashboardController, 'metrics']);
$router->get('/account', [$accountController, 'index']);
$router->post('/account/change-password', [$accountController, 'changePassword']);
$router->get('/services', [$serviceController, 'index']);
$router->post('/services/control', [$serviceController, 'control']);
$router->post('/services/install', [$serviceController, 'installPackage']);
$router->post('/services/remove', [$serviceController, 'removePackage']);
$router->post('/services/docker/pull', [$serviceController, 'dockerPull']);
$router->get('/users', [$usersController, 'index']);
$router->post('/users/create', [$usersController, 'create']);
$router->post('/users/update-role', [$usersController, 'updateRole']);
$router->post('/users/toggle-active', [$usersController, 'toggleActive']);
$router->get('/logs', [$observabilityController, 'logs']);
$router->get('/logs/export', [$observabilityController, 'exportAudit']);
$router->post('/logs/prune', [$observabilityController, 'pruneAudit']);
$router->get('/jobs', [$observabilityController, 'jobs']);
$router->get('/sites', [$siteController, 'index']);
$router->get('/sites/create-wizard', [$siteController, 'createWizard']);
$router->post('/sites/create-wizard', [$siteController, 'createWizardSubmit']);
$router->get('/sites/overview', [$siteController, 'overview']);
$router->get('/sites/domains', [$siteController, 'domains']);
$router->get('/sites/runtime', [$siteController, 'runtime']);
$router->get('/sites/proxy', [$siteController, 'proxy']);
$router->get('/sites/deploy', [$siteController, 'deploy']);
$router->get('/sites/files', [$siteController, 'files']);
$router->get('/sites/file/download', [$siteController, 'downloadFile']);
$router->get('/sites/mail', [$siteController, 'mail']);
$router->get('/sites/dns', [$siteController, 'dns']);
$router->get('/sites/backups', [$siteController, 'backups']);
$router->get('/sites/security', [$siteController, 'security']);
$router->get('/sites/ssl', [$siteController, 'ssl']);
$router->post('/sites/create', [$siteController, 'create']);
$router->post('/sites/domain/add', [$siteController, 'addDomain']);
$router->post('/sites/domain/delete', [$siteController, 'deleteDomain']);
$router->post('/sites/ssl/action', [$siteController, 'sslAction']);
$router->post('/sites/ssl/force-https', [$siteController, 'saveForceHttps']);
$router->post('/sites/delete', [$siteController, 'deleteSite']);
$router->post('/sites/php/save', [$siteController, 'savePhp']);
$router->post('/sites/runtime/control', [$siteController, 'runtimeControl']);
$router->post('/sites/database/create', [$siteController, 'createDatabase']);
$router->post('/sites/database/delete', [$siteController, 'deleteDatabase']);
$router->post('/sites/database/reset-user', [$siteController, 'resetDatabaseUser']);
$router->post('/sites/wordpress/install', [$siteController, 'installWordPress']);
$router->post('/sites/wordpress/plugin', [$siteController, 'wordpressPlugin']);
$router->post('/sites/wordpress/theme', [$siteController, 'wordpressTheme']);
$router->post('/sites/wordpress/core-update', [$siteController, 'wordpressCoreUpdate']);
$router->post('/sites/wordpress/maintenance', [$siteController, 'wordpressMaintenance']);
$router->post('/sites/wordpress/staging/create', [$siteController, 'createWordPressStaging']);
$router->post('/sites/wordpress/staging/sync-live', [$siteController, 'syncWordPressStagingToLive']);
$router->post('/sites/wordpress/security-scan', [$siteController, 'scanWordPressSecurity']);
$router->post('/sites/backup/create', [$siteController, 'createBackup']);
$router->post('/sites/backup/dry-run', [$siteController, 'dryRunRestore']);
$router->post('/sites/backup/restore', [$siteController, 'restoreBackup']);
$router->post('/sites/backup/cleanup', [$siteController, 'cleanupBackups']);
$router->post('/sites/backup/schedule', [$siteController, 'saveBackupSchedule']);
$router->post('/sites/file/save', [$siteController, 'saveFile']);
$router->post('/sites/file/upload', [$siteController, 'uploadFile']);
$router->post('/sites/file/move', [$siteController, 'moveFile']);
$router->post('/sites/file/copy', [$siteController, 'copyFile']);
$router->post('/sites/file/bulk-delete', [$siteController, 'bulkDeleteFiles']);
$router->post('/sites/file/delete', [$siteController, 'deleteFile']);
$router->post('/sites/file/create-directory', [$siteController, 'createDirectory']);
$router->post('/sites/file/rename', [$siteController, 'renamePath']);
$router->post('/sites/ftp/create', [$siteController, 'createFtpAccount']);
$router->post('/sites/ftp/reset-password', [$siteController, 'resetFtpAccountPassword']);
$router->post('/sites/ftp/set-status', [$siteController, 'setFtpAccountStatus']);
$router->post('/sites/mailbox/create', [$siteController, 'createMailbox']);
$router->post('/sites/mail/domain-records/apply', [$siteController, 'applyMailDomainRecords']);
$router->post('/sites/mail/webmail/save', [$siteController, 'saveWebmailSettings']);
$router->post('/sites/mailbox/delete', [$siteController, 'deleteMailbox']);
$router->post('/sites/mail/queue/retry', [$siteController, 'retryMailQueueItem']);
$router->post('/sites/mail/queue/remove', [$siteController, 'removeMailQueueItem']);
$router->post('/sites/dns/add-record', [$siteController, 'addDnsRecord']);
$router->post('/sites/dns/delete-record', [$siteController, 'deleteDnsRecord']);
$router->post('/sites/proxy/add-route', [$siteController, 'addProxyRoute']);
$router->post('/sites/proxy/delete-route', [$siteController, 'deleteProxyRoute']);
$router->post('/sites/deploy/save', [$siteController, 'saveDeployProfile']);
$router->post('/sites/deploy/run', [$siteController, 'runDeploy']);

return [
    'router' => $router,
    'config' => [
        'app' => $appConfig,
        'database' => $dbConfig,
        'paths' => $paths,
    ],
];
