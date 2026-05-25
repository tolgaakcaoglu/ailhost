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

final class AuthController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly AuditLogService $auditLogService,
        private readonly CsrfService $csrfService,
        private readonly FlashService $flashService
    )
    {
    }

    public function showLogin(Request $request): Response
    {
        if ($this->authService->isAuthenticated()) {
            return Response::redirect('/dashboard');
        }

        $toast = $this->flashService->consumeToast();

        return new Response(View::render('auth/login', [
            'title' => 'Yönetici Girişi',
            'toastMessage' => (string) ($toast['message'] ?? ''),
            'toastType' => (string) ($toast['type'] ?? 'info'),
            'toastPersistent' => (bool) ($toast['persistent'] ?? false),
            'csrfToken' => $this->csrfService->token(),
        ]));
    }

    public function showForgotPassword(Request $request): Response
    {
        if ($this->authService->isAuthenticated()) {
            return Response::redirect('/dashboard');
        }

        $toast = $this->flashService->consumeToast();

        return new Response(View::render('auth/forgot-password', [
            'title' => 'Şifre Sıfırla',
            'toastMessage' => (string) ($toast['message'] ?? ''),
            'toastType' => (string) ($toast['type'] ?? 'info'),
            'toastPersistent' => (bool) ($toast['persistent'] ?? false),
            'csrfToken' => $this->csrfService->token(),
        ]));
    }


    public function login(Request $request): Response
    {
        if (($guard = $this->guardCsrf($request)) !== null) {
            return $guard;
        }

        $result = $this->authService->login(
            (string) $request->input('email', ''),
            (string) $request->input('password', ''),
            (string) $request->input('remember_me', '') === '1'
        );

        if (($result['ok'] ?? false) === true) {
            $email = (string) $request->input('email', '');
            $this->auditLogService->log($email, 'auth.login.success', 'session', $email, [
                'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            ]);
            $this->flashService->setToast('Giriş başarılı.', 'info');
            return Response::redirect('/dashboard');
        }

        $email = trim((string) $request->input('email', ''));
        $this->auditLogService->log($email !== '' ? $email : null, 'auth.login.failed', 'session', $email !== '' ? $email : 'unknown', [
            'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            'reason' => (string) ($result['message'] ?? 'Giriş başarısız.'),
        ]);
        $this->flashService->setToast((string) ($result['message'] ?? 'Giriş başarısız.'), 'error');
        return Response::redirect('/login');
    }

    public function logout(Request $request): Response
    {
        if (($guard = $this->guardCsrf($request)) !== null) {
            return $guard;
        }

        $this->auditLogService->log($this->authService->currentEmail(), 'auth.logout', 'session', $this->authService->currentEmail(), [
            'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ]);
        $this->authService->logout();
        $this->flashService->setToast('Çıkış yapıldı.', 'info');
        return Response::redirect('/login');
    }

    public function forgotPassword(Request $request): Response
    {
        if (($guard = $this->guardCsrf($request)) !== null) {
            return $guard;
        }

        $email = trim((string) $request->input('email', ''));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->flashService->setToast('Geçerli bir e-posta adresi girin.', 'error');
            return Response::redirect('/forgot-password');
        }

        $this->auditLogService->log($email, 'auth.password_reset.requested', 'session', $email, [
            'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ]);
        $this->flashService->setToast('Şifre sıfırlama isteği alındı.', 'info');
        return Response::redirect('/forgot-password');
    }

    private function guardCsrf(Request $request): ?Response
    {
        if ($this->csrfService->isValid((string) $request->input('_csrf', ''))) {
            return null;
        }
        $this->flashService->setToast('İstek doğrulaması başarısız. Sayfayı yenileyin.', 'error');
        return Response::redirect('/login');
    }
}
