<?php

declare(strict_types=1);

namespace Ailhost\Services;

final class AuthService
{
    public function __construct(
        private readonly InstallService $installService,
        private readonly UserService $userService,
        private readonly RateLimiterService $rateLimiterService,
        private readonly array $paths
    )
    {
    }

    public function isAuthenticated(): bool
    {
        return isset($_SESSION['auth_email']) && is_string($_SESSION['auth_email']) && $_SESSION['auth_email'] !== '';
    }

    public function currentEmail(): string
    {
        return (string) ($_SESSION['auth_email'] ?? '');
    }

    public function currentRole(): string
    {
        $sessionRole = (string) ($_SESSION['auth_role'] ?? '');
        if (in_array($sessionRole, ['owner', 'admin', 'developer', 'viewer'], true)) {
            return $sessionRole;
        }

        $saved = $this->installService->adminData();
        $savedEmail = (string) ($saved['email'] ?? '');
        $savedRole = (string) ($saved['role'] ?? 'owner');
        if ($this->currentEmail() !== '' && $this->currentEmail() === $savedEmail) {
            return in_array($savedRole, ['owner', 'admin', 'developer', 'viewer'], true) ? $savedRole : 'owner';
        }

        return 'viewer';
    }

    public function can(string $permission): bool
    {
        $role = $this->currentRole();
        if ($role === 'owner') {
            return true;
        }

        $matrix = [
            'admin' => [
                'site.delete',
                'backup.restore',
                'dns.delete',
                'deploy.run',
                'runtime.control',
                'users.manage',
            ],
            'developer' => [
                'deploy.run',
                'runtime.control',
            ],
            'viewer' => [],
        ];

        return in_array($permission, $matrix[$role] ?? [], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function login(string $email, string $password, bool $remember = false): array
    {
        $email = trim($email);
        $saved = $this->installService->adminData();
        if ((string) ($saved['email'] ?? '') === '' || (string) ($saved['password_hash'] ?? '') === '') {
            return ['ok' => false, 'message' => 'Önce kurulumda yönetici oluşturulmalı.'];
        }
        $ip = $this->clientIp();
        $buckets = $this->throttleBuckets($email, $ip);
        $throttle = $this->rateLimiterService->check($buckets);
        if (($throttle['locked'] ?? false) === true) {
            return ['ok' => false, 'message' => 'Çok fazla başarısız deneme. Lütfen biraz bekleyin.'];
        }

        $user = $this->userService->findByEmail($email);
        if ($user === null) {
            $this->rateLimiterService->hit($buckets);
            return ['ok' => false, 'message' => 'E-posta veya şifre hatalı.'];
        }
        if (((bool) ($user['active'] ?? true)) !== true) {
            return ['ok' => false, 'message' => 'Hesap pasif durumda.'];
        }
        if (!password_verify($password, (string) ($user['password_hash'] ?? ''))) {
            $this->rateLimiterService->hit($buckets);
            return ['ok' => false, 'message' => 'E-posta veya şifre hatalı.'];
        }
        $this->rateLimiterService->clear([
            $this->bucketKeyEmail($email),
            $this->bucketKeyEmailIp($email, $ip),
        ]);

        $_SESSION['auth_email'] = (string) ($user['email'] ?? '');
        $savedRole = (string) ($user['role'] ?? 'owner');
        if (!in_array($savedRole, ['owner', 'admin', 'developer', 'viewer'], true)) {
            $savedRole = 'owner';
        }
        $_SESSION['auth_role'] = $savedRole;
        $_SESSION['auth_remember'] = $remember ? '1' : '0';
        $this->setRememberCookie($remember);
        $this->userService->touchLoginByEmail((string) ($user['email'] ?? ''));
        return ['ok' => true];
    }

    /**
     * @return array<string, mixed>
     */
    public function changeCurrentUserPassword(string $currentPassword, string $newPassword): array
    {
        if (!$this->isAuthenticated()) {
            return ['ok' => false, 'message' => 'Önce giriş yapın.'];
        }
        return $this->userService->updatePasswordByEmail($this->currentEmail(), $currentPassword, $newPassword);
    }

    public function logout(): void
    {
        $email = $this->currentEmail();
        if ($email !== '') {
            $this->userService->touchLogoutByEmail($email);
        }
        unset($_SESSION['auth_email']);
        unset($_SESSION['auth_role']);
        unset($_SESSION['auth_remember']);
        $this->setRememberCookie(false);
    }

    private function attemptKey(string $email, string $ip): string
    {
        return mb_strtolower(trim($email)) . '|' . $ip;
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function throttleBuckets(string $email, string $ip): array
    {
        return [
            $this->bucketKeyGlobal() => ['max_attempts' => 60, 'window_seconds' => 900, 'lock_seconds' => 300],
            $this->bucketKeyIp($ip) => ['max_attempts' => 20, 'window_seconds' => 900, 'lock_seconds' => 900],
            $this->bucketKeyEmail($email) => ['max_attempts' => 10, 'window_seconds' => 900, 'lock_seconds' => 900],
            $this->bucketKeyEmailIp($email, $ip) => ['max_attempts' => 5, 'window_seconds' => 900, 'lock_seconds' => 900],
        ];
    }

    private function bucketKeyGlobal(): string
    {
        return 'global';
    }

    private function bucketKeyIp(string $ip): string
    {
        return 'ip:' . $ip;
    }

    private function bucketKeyEmail(string $email): string
    {
        return 'email:' . mb_strtolower(trim($email));
    }

    private function bucketKeyEmailIp(string $email, string $ip): string
    {
        return 'email_ip:' . $this->attemptKey($email, $ip);
    }

    private function clientIp(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        return $ip !== '' ? $ip : '0.0.0.0';
    }

    private function setRememberCookie(bool $remember): void
    {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        if ($remember) {
            setcookie('ailhost_remember', '1', [
                'expires' => time() + (60 * 60 * 24 * 30),
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            return;
        }

        setcookie('ailhost_remember', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
