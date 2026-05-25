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
use Ailhost\Services\InstallService;
use Ailhost\Services\JobService;

final class InstallController
{
    public function __construct(
        private readonly InstallService $installService,
        private readonly JobService $jobService,
        private readonly AuthService $authService,
        private readonly AuditLogService $auditLogService,
        private readonly CsrfService $csrfService,
        private readonly FlashService $flashService
    )
    {
    }

    public function index(Request $request): Response
    {
        if ($this->installService->isInstalled()) {
            if ($this->authService->isAuthenticated()) {
                return Response::redirect('/dashboard');
            }

            $this->flashService->setToast('Kurulum tamamlandı. Giriş yapın.', 'info');
            return Response::redirect('/login');
        }

        return $this->renderInstall('', true, (string) $request->query('step', ''));
    }

    public function saveAdmin(Request $request): Response
    {
        if (($guard = $this->guardInstallWrite($request, 'admin')) !== null) {
            return $guard;
        }

        $password = (string) $request->input('password', '');
        $passwordConfirm = (string) $request->input('password_confirm', '');
        if ($passwordConfirm !== '' && $passwordConfirm !== $password) {
            $result = ['ok' => false, 'message' => 'Şifreler uyuşmuyor.'];
        } else {
            $result = $this->installService->saveAdmin(
                (string) $request->input('email', ''),
                $password,
                (string) $request->input('name', '')
            );
        }

        $this->flashService->setToast((string) ($result['message'] ?? ''), ($result['ok'] ?? false) ? 'info' : 'error');
        $this->auditLogService->log(
            (string) $request->input('email', ''),
            ($result['ok'] ?? false) ? 'install.admin.saved' : 'install.admin.save_failed',
            'installer',
            'admin'
        );

        if (($result['ok'] ?? false) === true && (string) $request->input('finish_install', '') === '1') {
            $lockResult = $this->installService->lockInstall();
            if (($lockResult['ok'] ?? false) === true) {
                $this->flashService->setToast('Kurulum tamamlandı. Giriş yapın.', 'info');
                return Response::redirect('/login');
            }

            $this->flashService->setToast((string) ($lockResult['message'] ?? ''), 'error');
            return Response::redirect('/install?step=admin');
        }

        return Response::redirect(($result['ok'] ?? false) ? '/install?step=admin' : '/install?step=admin');
    }

    public function saveSettings(Request $request): Response
    {
        if (($guard = $this->guardInstallWrite($request, 'settings')) !== null) {
            return $guard;
        }

        $result = $this->installService->saveSettings(
            (string) $request->input('server_name', (string) $request->input('hostname', '')),
            (string) $request->input('server_ip', '127.0.0.1'),
            (string) $request->input('nameserver_1', ''),
            (string) $request->input('nameserver_2', ''),
            [
                'timezone' => (string) $request->input('timezone', 'UTC'),
                'log_retention_days' => (int) $request->input('log_retention_days', 30),
                'default_web_root' => (string) $request->input('default_web_root', '/var/www/html'),
                'backup_path' => (string) $request->input('backup_path', '/mnt/backups/ailhost'),
                'create_default_web_root' => (string) $request->input('create_default_web_root', '') === '1',
                'create_backup_path' => (string) $request->input('create_backup_path', '') === '1',
                'sudo_password' => (string) $request->input('sudo_password', ''),
            ]
        );

        $this->flashService->setToast((string) ($result['message'] ?? ''), ($result['ok'] ?? false) ? 'info' : 'error');
        $this->auditLogService->log(
            $this->authService->currentEmail() !== '' ? $this->authService->currentEmail() : null,
            ($result['ok'] ?? false) ? 'install.settings.saved' : 'install.settings.save_failed',
            'installer',
            'settings'
        );
        return Response::redirect(($result['ok'] ?? false) ? '/install?step=admin' : '/install?step=settings');
    }

    public function lock(Request $request): Response
    {
        if (($guard = $this->guardInstallWrite($request, 'finish')) !== null) {
            return $guard;
        }

        $result = $this->installService->lockInstall();

        if (($result['ok'] ?? false) === true) {
            $this->auditLogService->log($this->authService->currentEmail(), 'install.locked', 'installer', 'state');
            $this->flashService->setToast('Kurulum kilitlendi. Giriş yapın.', 'info');
            return Response::redirect('/login');
        }

        $this->flashService->setToast((string) ($result['message'] ?? ''), 'error');
        return Response::redirect('/install?step=finish');
    }

    public function saveServices(Request $request): Response
    {
        if (($guard = $this->guardInstallWrite($request, 'services')) !== null) {
            return $guard;
        }

        $result = $this->installService->saveServices(
            (string) $request->input('web_server', ''),
            (string) $request->input('dns_server', ''),
            (string) $request->input('firewall', '')
        );

        $this->flashService->setToast((string) ($result['message'] ?? ''), ($result['ok'] ?? false) ? 'info' : 'error');
        $this->auditLogService->log(
            $this->authService->currentEmail() !== '' ? $this->authService->currentEmail() : null,
            ($result['ok'] ?? false) ? 'install.services.saved' : 'install.services.save_failed',
            'installer',
            'services'
        );
        return Response::redirect(($result['ok'] ?? false) ? '/install?step=finish' : '/install?step=services');
    }

    public function retryFailedJob(Request $request): Response
    {
        if (($guard = $this->guardInstallWrite($request)) !== null) {
            return $guard;
        }

        $result = $this->installService->retryLastFailedJob();
        $this->flashService->setToast((string) ($result['message'] ?? ''), ($result['ok'] ?? false) ? 'info' : 'error');
        $this->auditLogService->log(
            $this->authService->currentEmail() !== '' ? $this->authService->currentEmail() : null,
            ($result['ok'] ?? false) ? 'install.job.retry' : 'install.job.retry_failed',
            'job',
            'last_failed'
        );
        return Response::redirect('/install');
    }

    public function latestJobStatus(): Response
    {
        $latest = $this->jobService->latest();
        return Response::json([
            'ok' => true,
            'job' => $latest,
        ]);
    }

    public function installSnapshot(): Response
    {
        return Response::json([
            'ok' => true,
            'jobs' => $this->installService->jobs(),
            'logs' => array_slice($this->installService->logs(), -6),
            'state' => $this->installService->state(),
        ]);
    }

    public function directorySuggestions(Request $request): Response
    {
        if ($this->installService->isInstalled()) {
            return Response::json(['ok' => false, 'suggestions' => [], 'can_create' => false, 'create_candidate' => '']);
        }

        return Response::json($this->installService->directorySuggestions((string) $request->query('path', '')));
    }

    private function lockedInstallResponse(): Response
    {
        if ($this->authService->isAuthenticated()) {
            $this->flashService->setToast('Kurulum kilitli.', 'info');
            return Response::redirect('/dashboard');
        }

        $this->flashService->setToast('Kurulum kilitli. Giriş yapın.', 'info');
        return Response::redirect('/login');
    }

    private function guardInstallWrite(Request $request, string $step = ''): ?Response
    {
        if ($this->installService->isInstalled()) {
            return $this->lockedInstallResponse();
        }
        if ($this->csrfService->isValid((string) $request->input('_csrf', ''))) {
            return null;
        }
        $this->flashService->setToast('İstek doğrulaması başarısız. Sayfayı yenileyin.', 'error');
        return Response::redirect($step === '' ? '/install' : '/install?step=' . $step);
    }

    private function renderInstall(string $message = '', bool $ok = true, string $preferredStep = ''): Response
    {
        $toast = $this->flashService->consumeToast();
        $requirements = $this->installService->requirements();
        $state = $this->installService->state();
        $jobs = $this->installService->jobs();
        $logs = $this->installService->logs();
        $adminData = $this->installService->adminData();
        $settingsData = $this->installService->settingsData();
        $servicesData = $this->installService->servicesData();

        return new Response(View::render('install/index', [
            'title' => 'İlk Kurulum',
            'isInstalled' => $this->installService->isInstalled(),
            'requirements' => $requirements,
            'allRequirementsOk' => $this->installService->allRequirementsOk(),
            'state' => $state,
            'message' => $message,
            'messageOk' => $ok,
            'jobs' => $jobs,
            'logs' => $logs,
            'preferredStep' => $preferredStep,
            'adminData' => $adminData,
            'settingsData' => $settingsData,
            'servicesData' => $servicesData,
            'csrfToken' => $this->csrfService->token(),
            'toastMessage' => (string) ($toast['message'] ?? $message),
            'toastType' => (string) ($toast['type'] ?? (($ok ? 'info' : 'error'))),
            'toastPersistent' => (bool) ($toast['persistent'] ?? false),
        ]));
    }
}
