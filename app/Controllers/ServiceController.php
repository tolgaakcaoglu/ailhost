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

final class ServiceController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly ServiceOpsService $serviceOpsService,
        private readonly JobService $jobService,
        private readonly AuditLogService $auditLogService,
        private readonly CsrfService $csrfService,
        private readonly FlashService $flashService
    ) {
    }

    public function index(Request $request): Response
    {
        if (($guard = $this->guardAuthenticated()) !== null) {
            return $guard;
        }
        $toast = $this->flashService->consumeToast();
        $selectedContainer = trim((string) $request->query('container', ''));
        $dockerLogs = $selectedContainer !== '' ? $this->serviceOpsService->dockerLogs($selectedContainer, 120) : [];
        return new Response(View::render('services/index', [
            'title' => 'Servis Sağlığı',
            'layoutMode' => 'app',
            'navActive' => 'services',
            'rows' => $this->serviceOpsService->health(),
            'dockerContainers' => $this->serviceOpsService->dockerContainers(),
            'selectedContainer' => $selectedContainer,
            'dockerLogs' => $dockerLogs,
            'topbarJobSummary' => $this->jobSummary(),
            'authEmail' => $this->authService->currentEmail(),
            'authRole' => $this->authService->currentRole(),
            'csrfToken' => $this->csrfService->token(),
            'toastMessage' => (string) ($toast['message'] ?? ''),
            'toastType' => (string) ($toast['type'] ?? 'info'),
            'toastPersistent' => (bool) ($toast['persistent'] ?? false),
        ]));
    }

    public function control(Request $request): Response
    {
        if (($guard = $this->guardWrite($request, 'runtime.control')) !== null) {
            return $guard;
        }
        $service = (string) $request->input('service', '');
        $action = (string) $request->input('action_type', '');
        $result = $this->serviceOpsService->requestControl($service, $action);
        $ok = (bool) ($result['ok'] ?? false);
        $redirectTo = $this->safeRedirect((string) $request->input('redirect_to', '/services'));
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            $ok ? 'service.control.requested' : 'service.control.request_failed',
            'service',
            $service,
            ['action' => $action]
        );
        $this->flashService->setToast($ok ? 'Servis işi kuyruğa alındı.' : (string) ($result['message'] ?? 'Servis işi başlatılamadı.'), $ok ? 'info' : 'error');
        return Response::redirect($redirectTo);
    }

    public function dockerPull(Request $request): Response
    {
        if (($guard = $this->guardWrite($request, 'runtime.control')) !== null) {
            return $guard;
        }
        $image = trim((string) $request->input('image', ''));
        $result = $this->serviceOpsService->requestDockerImagePull($image);
        $ok = (bool) ($result['ok'] ?? false);
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            $ok ? 'docker.image.pull_requested' : 'docker.image.pull_request_failed',
            'docker',
            $image
        );
        $this->flashService->setToast($ok ? 'Docker image pull işi kuyruğa alındı.' : (string) ($result['message'] ?? 'Image pull başlatılamadı.'), $ok ? 'info' : 'error');
        return Response::redirect('/services');
    }

    public function installPackage(Request $request): Response
    {
        if (($guard = $this->guardWrite($request, 'runtime.control')) !== null) {
            return $guard;
        }
        $service = trim((string) $request->input('service', ''));
        $redirectTo = $this->safeRedirect((string) $request->input('redirect_to', '/services'));
        $result = $this->serviceOpsService->requestPackageInstall($service);
        $ok = (bool) ($result['ok'] ?? false);
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            $ok ? 'service.package_install.requested' : 'service.package_install.request_failed',
            'service',
            $service
        );
        $this->flashService->setToast($ok ? 'Paket kurulum işi kuyruğa alındı.' : (string) ($result['message'] ?? 'Paket kurulum işi başlatılamadı.'), $ok ? 'info' : 'error');
        return Response::redirect($redirectTo);
    }

    public function removePackage(Request $request): Response
    {
        if (($guard = $this->guardWrite($request, 'runtime.control')) !== null) {
            return $guard;
        }
        $service = trim((string) $request->input('service', ''));
        $redirectTo = $this->safeRedirect((string) $request->input('redirect_to', '/services'));
        $result = $this->serviceOpsService->requestPackageRemove($service);
        $ok = (bool) ($result['ok'] ?? false);
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            $ok ? 'service.package_remove.requested' : 'service.package_remove.request_failed',
            'service',
            $service
        );
        $this->flashService->setToast($ok ? 'Paket kaldırma işi kuyruğa alındı.' : (string) ($result['message'] ?? 'Paket kaldırma işi başlatılamadı.'), $ok ? 'info' : 'error');
        return Response::redirect($redirectTo);
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

    private function safeRedirect(string $path): string
    {
        $path = trim($path);
        if ($path === '' || !str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return '/services';
        }

        return preg_match('#^/[a-zA-Z0-9/_?=&.-]{0,180}$#', $path) === 1 ? $path : '/services';
    }

    private function guardWrite(Request $request, string $permission): ?Response
    {
        if (($guard = $this->guardAuthenticated()) !== null) {
            return $guard;
        }
        if (!$this->authService->can($permission)) {
            $this->flashService->setToast('Bu işlem için yetkiniz yok.', 'error');
            return Response::redirect('/services');
        }
        if ($this->csrfService->isValid((string) $request->input('_csrf', ''))) {
            return null;
        }
        $this->flashService->setToast('İstek doğrulaması başarısız. Sayfayı yenileyin.', 'error');
        return Response::redirect('/services');
    }
}
