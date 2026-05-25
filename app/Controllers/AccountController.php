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

final class AccountController
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
        if (($guard = $this->guardAuthenticated()) !== null) {
            return $guard;
        }

        $toast = $this->flashService->consumeToast();
        $user = $this->userService->findByEmail($this->authService->currentEmail());

        return new Response(View::render('account/index', [
            'title' => 'Hesabım',
            'layoutMode' => 'app',
            'navActive' => 'account',
            'user' => $user ?? [],
            'csrfToken' => $this->csrfService->token(),
            'authEmail' => $this->authService->currentEmail(),
            'authRole' => $this->authService->currentRole(),
            'toastMessage' => (string) ($toast['message'] ?? ''),
            'toastType' => (string) ($toast['type'] ?? 'info'),
            'toastPersistent' => (bool) ($toast['persistent'] ?? false),
            'topbarJobSummary' => $this->jobSummary(),
        ]));
    }

    public function changePassword(Request $request): Response
    {
        if (($guard = $this->guardPost($request)) !== null) {
            return $guard;
        }

        $currentPassword = (string) $request->input('current_password', '');
        $newPassword = (string) $request->input('new_password', '');
        $newPasswordConfirm = (string) $request->input('new_password_confirm', '');
        if ($newPassword !== $newPasswordConfirm) {
            $this->flashService->setToast('Yeni şifre tekrarı eşleşmiyor.', 'error');
            return Response::redirect('/account');
        }

        $result = $this->authService->changeCurrentUserPassword($currentPassword, $newPassword);
        $ok = (bool) ($result['ok'] ?? false);
        $this->auditLogService->log(
            $this->authService->currentEmail(),
            $ok ? 'account.password_change' : 'account.password_change_failed',
            'user',
            $this->authService->currentEmail()
        );
        $this->flashService->setToast((string) ($result['message'] ?? ''), $ok ? 'info' : 'error');
        return Response::redirect('/account');
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

    private function guardPost(Request $request): ?Response
    {
        if (($guard = $this->guardAuthenticated()) !== null) {
            return $guard;
        }
        if ($this->csrfService->isValid((string) $request->input('_csrf', ''))) {
            return null;
        }
        $this->flashService->setToast('İstek doğrulaması başarısız. Sayfayı yenileyin.', 'error');
        return Response::redirect('/account');
    }
}
