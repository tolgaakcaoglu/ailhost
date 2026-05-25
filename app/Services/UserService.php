<?php

declare(strict_types=1);

namespace Ailhost\Services;

final class UserService
{
    public function __construct(
        private readonly array $paths,
        private readonly InstallService $installService,
        private readonly JsonStateStore $jsonStateStore = new JsonStateStore()
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $users = $this->readUsers();
        usort($users, static fn(array $a, array $b): int => strcmp((string) ($a['email'] ?? ''), (string) ($b['email'] ?? '')));
        return $users;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        $email = mb_strtolower(trim($email));
        if ($email === '') {
            return null;
        }
        foreach ($this->readUsers() as $user) {
            if (mb_strtolower((string) ($user['email'] ?? '')) === $email) {
                return $user;
            }
        }
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function create(string $email, string $password, string $role): array
    {
        $email = trim($email);
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return ['ok' => false, 'message' => 'Geçerli bir e-posta gerekli.'];
        }
        if (!$this->isValidRole($role)) {
            return ['ok' => false, 'message' => 'Geçersiz rol seçimi.'];
        }
        if (strlen($password) < 8) {
            return ['ok' => false, 'message' => 'Şifre en az 8 karakter olmalı.'];
        }
        if ($this->findByEmail($email) !== null) {
            return ['ok' => false, 'message' => 'Bu e-posta ile kayıtlı kullanıcı zaten var.'];
        }

        $users = $this->readUsers();
        $users[] = [
            'id' => bin2hex(random_bytes(8)),
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'active' => true,
            'created_at' => date(DATE_ATOM),
            'updated_at' => date(DATE_ATOM),
        ];
        $ok = $this->writeUsers($users);
        return $ok ? ['ok' => true, 'message' => 'Kullanıcı oluşturuldu.'] : ['ok' => false, 'message' => 'Kullanıcı kaydedilemedi.'];
    }

    /**
     * @return array<string, mixed>
     */
    public function updateRole(string $userId, string $role): array
    {
        if (!$this->isValidRole($role)) {
            return ['ok' => false, 'message' => 'Geçersiz rol seçimi.'];
        }
        $users = $this->readUsers();
        $found = false;
        foreach ($users as &$user) {
            if ((string) ($user['id'] ?? '') !== $userId) {
                continue;
            }
            $user['role'] = $role;
            $user['updated_at'] = date(DATE_ATOM);
            $found = true;
            break;
        }
        unset($user);
        if (!$found) {
            return ['ok' => false, 'message' => 'Kullanıcı bulunamadı.'];
        }
        if (!$this->hasOwner($users)) {
            return ['ok' => false, 'message' => 'En az bir owner kullanıcı olmalı.'];
        }
        $ok = $this->writeUsers($users);
        return $ok ? ['ok' => true, 'message' => 'Rol güncellendi.'] : ['ok' => false, 'message' => 'Rol güncellenemedi.'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toggleActive(string $userId): array
    {
        $users = $this->readUsers();
        $found = false;
        foreach ($users as &$user) {
            if ((string) ($user['id'] ?? '') !== $userId) {
                continue;
            }
            $user['active'] = !((bool) ($user['active'] ?? true));
            $user['updated_at'] = date(DATE_ATOM);
            $found = true;
            break;
        }
        unset($user);
        if (!$found) {
            return ['ok' => false, 'message' => 'Kullanıcı bulunamadı.'];
        }
        if (!$this->hasActiveOwner($users)) {
            return ['ok' => false, 'message' => 'En az bir aktif owner kullanıcı olmalı.'];
        }
        $ok = $this->writeUsers($users);
        return $ok ? ['ok' => true, 'message' => 'Kullanıcı durumu güncellendi.'] : ['ok' => false, 'message' => 'Kullanıcı durumu güncellenemedi.'];
    }

    /**
     * @return array<int, string>
     */
    public function availableRoles(): array
    {
        return ['owner', 'admin', 'developer', 'viewer'];
    }

    /**
     * @return array<string, mixed>
     */
    public function updatePasswordByEmail(string $email, string $currentPassword, string $newPassword): array
    {
        $email = mb_strtolower(trim($email));
        if ($email === '') {
            return ['ok' => false, 'message' => 'Kullanıcı doğrulanamadı.'];
        }
        if (strlen($newPassword) < 8) {
            return ['ok' => false, 'message' => 'Yeni şifre en az 8 karakter olmalı.'];
        }

        $users = $this->readUsers();
        $found = false;
        foreach ($users as &$user) {
            if (mb_strtolower((string) ($user['email'] ?? '')) !== $email) {
                continue;
            }
            if (!password_verify($currentPassword, (string) ($user['password_hash'] ?? ''))) {
                return ['ok' => false, 'message' => 'Mevcut şifre hatalı.'];
            }
            $user['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
            $user['updated_at'] = date(DATE_ATOM);
            $found = true;
            break;
        }
        unset($user);

        if (!$found) {
            return ['ok' => false, 'message' => 'Kullanıcı bulunamadı.'];
        }
        $ok = $this->writeUsers($users);
        return $ok ? ['ok' => true, 'message' => 'Şifre güncellendi.'] : ['ok' => false, 'message' => 'Şifre güncellenemedi.'];
    }

    public function touchLoginByEmail(string $email): void
    {
        $this->touchByEmail($email, 'last_login_at');
    }

    public function touchLogoutByEmail(string $email): void
    {
        $this->touchByEmail($email, 'last_logout_at');
    }

    private function isValidRole(string $role): bool
    {
        return in_array($role, $this->availableRoles(), true);
    }

    private function touchByEmail(string $email, string $field): void
    {
        $email = mb_strtolower(trim($email));
        if ($email === '') {
            return;
        }
        $users = $this->readUsers();
        $changed = false;
        foreach ($users as &$user) {
            if (mb_strtolower((string) ($user['email'] ?? '')) !== $email) {
                continue;
            }
            $user[$field] = date(DATE_ATOM);
            $user['updated_at'] = date(DATE_ATOM);
            $changed = true;
            break;
        }
        unset($user);
        if ($changed) {
            $this->writeUsers($users);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readUsers(): array
    {
        $file = $this->usersFile();
        $decoded = [];
        if (is_file($file)) {
            $decoded = $this->jsonStateStore->readArray($file);
        }
        if (!is_array($decoded)) {
            $decoded = [];
        }

        $users = [];
        foreach ($decoded as $row) {
            if (is_array($row) && is_string($row['email'] ?? null)) {
                $users[] = $row;
            }
        }

        if ($users === []) {
            $admin = $this->installService->adminData();
            $adminEmail = (string) ($admin['email'] ?? '');
            $adminHash = (string) ($admin['password_hash'] ?? '');
            if ($adminEmail !== '' && $adminHash !== '') {
                $users[] = [
                    'id' => bin2hex(random_bytes(8)),
                    'email' => $adminEmail,
                    'password_hash' => $adminHash,
                    'role' => in_array((string) ($admin['role'] ?? 'owner'), $this->availableRoles(), true) ? (string) $admin['role'] : 'owner',
                    'active' => true,
                    'created_at' => (string) ($admin['created_at'] ?? date(DATE_ATOM)),
                    'updated_at' => date(DATE_ATOM),
                ];
                $this->writeUsers($users);
            }
        }

        return $users;
    }

    /**
     * @param array<int, array<string, mixed>> $users
     */
    private function writeUsers(array $users): bool
    {
        return $this->jsonStateStore->writeArray($this->usersFile(), array_values($users));
    }

    private function usersFile(): string
    {
        return $this->paths['local_var_path'] . '/users.json';
    }

    /**
     * @param array<int, array<string, mixed>> $users
     */
    private function hasOwner(array $users): bool
    {
        foreach ($users as $user) {
            if ((string) ($user['role'] ?? '') === 'owner') {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $users
     */
    private function hasActiveOwner(array $users): bool
    {
        foreach ($users as $user) {
            if ((string) ($user['role'] ?? '') === 'owner' && ((bool) ($user['active'] ?? true)) === true) {
                return true;
            }
        }
        return false;
    }
}
