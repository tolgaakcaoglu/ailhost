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
use Ailhost\Services\UserService;

final class UsersController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly UserService $userService,
        private readonly JobService $jobService,
        private readonly AuditLogService $auditLogService,
        private readonly CsrfService $csrfService,
        private readonly FlashService $flashService
    ) {
    }

    public function index(Request $request): Response
    {
        if (!$this->authService->isAuthenticated()) {
            $this->flashService->setToast('Devam etmek için giriş yapın.', 'error');
            return Response::redirect('/login');
        }
        if (($deny = $this->denyUnlessManage()) !== null) {
            return $deny;
        }

        $toast = $this->flashService->consumeToast();

        return new Response(View::render('users/index', [
            'title' => 'Kullanıcı Yönetimi',
            'layoutMode' => 'app',
            'navActive' => 'users',
            'users' => $this->userService->all(),
            'roles' => $this->userService->availableRoles(),
            'csrfToken' => $this->csrfService->token(),
            'authEmail' => $this->authService->currentEmail(),
            'authRole' => $this->authService->currentRole(),
            'toastMessage' => (string) ($toast['message'] ?? ''),
            'toastType' => (string) ($toast['type'] ?? 'info'),
            'toastPersistent' => (bool) ($toast['persistent'] ?? false),
            'topbarJobSummary' => $this->jobSummary(),
        ]));
    }

    public function create(Request $request): Response
    {
        if (($guard = $this->guardPost($request)) !== null) {
            return $guard;
        }

        $result = $this->userService->create(
            (string) $request->input('email', ''),
            (string) $request->input('password', ''),
            (string) $request->input('role', 'viewer')
        );
        $ok = (bool) ($result['ok'] ?? false);
        $email = trim((string) $request->input('email', ''));
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            $ok ? 'user.create' : 'user.create_failed',
            'user',
            $email !== '' ? $email : 'unknown'
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), $ok ? 'info' : 'error');
        return Response::redirect('/users');
    }

    public function updateRole(Request $request): Response
    {
        if (($guard = $this->guardPost($request)) !== null) {
            return $guard;
        }

        $userId = (string) $request->input('user_id', '');
        $role = (string) $request->input('role', '');
        $result = $this->userService->updateRole($userId, $role);
        $ok = (bool) ($result['ok'] ?? false);
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            $ok ? 'user.role_update' : 'user.role_update_failed',
            'user',
            $userId,
            ['role' => $role]
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), $ok ? 'info' : 'error');
        return Response::redirect('/users');
    }

    public function toggleActive(Request $request): Response
    {
        if (($guard = $this->guardPost($request)) !== null) {
            return $guard;
        }

        $userId = (string) $request->input('user_id', '');
        $result = $this->userService->toggleActive($userId);
        $ok = (bool) ($result['ok'] ?? false);
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            $ok ? 'user.toggle_active' : 'user.toggle_active_failed',
            'user',
            $userId
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), $ok ? 'info' : 'error');
        return Response::redirect('/users');
    }

    private function guardPost(Request $request): ?Response
    {
        if (!$this->authService->isAuthenticated()) {
            $this->flashService->setToast('Devam etmek için giriş yapın.', 'error');
            return Response::redirect('/login');
        }
        if (($deny = $this->denyUnlessManage()) !== null) {
            return $deny;
        }
        if (!$this->csrfService->isValid((string) $request->input('_csrf', ''))) {
            $this->flashService->setToast('İstek doğrulaması başarısız. Sayfayı yenileyin.', 'error');
            return Response::redirect('/users');
        }
        return null;
    }

    private function denyUnlessManage(): ?Response
    {
        if ($this->authService->can('users.manage')) {
            return null;
        }
        $this->flashService->setToast('Bu işlem için yetkiniz yok.', 'error');
        return Response::redirect('/dashboard');
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
}
