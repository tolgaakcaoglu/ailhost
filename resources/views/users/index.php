<?php
declare(strict_types=1);

$users = is_array($users ?? null) ? $users : [];
$roles = is_array($roles ?? null) ? $roles : [];
$h = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

ob_start();
?>
<div class="page-header">
    <div>
        <h1>Kullanıcı Yönetimi</h1>
        <p>Panel kullanıcılarını, rollerini ve erişim durumlarını yönetin.</p>
    </div>
</div>

<section class="split-grid form-stack-top">
    <article class="module-card">
        <h2>Yeni Kullanıcı</h2>
        <form method="post" action="/users/create" class="form-grid form-stack-top">
            <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
            <div class="form-field-full">
                <label>E-posta</label>
                <input type="email" name="email" required placeholder="kullanici@example.com">
            </div>
            <div>
                <label>Şifre</label>
                <input type="password" name="password" required minlength="8" placeholder="En az 8 karakter">
            </div>
            <div>
                <label>Rol</label>
                <select name="role">
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= $h($role) ?>"><?= $h($role) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-actions form-field-full">
                <button type="submit">Kullanıcı Oluştur</button>
            </div>
        </form>
    </article>
    <article class="module-card">
        <h2>Rol Rehberi</h2>
        <table class="compact-table">
            <thead><tr><th>Rol</th><th>Açıklama</th></tr></thead>
            <tbody>
            <tr><td>owner</td><td>Tüm yönetim yetkileri</td></tr>
            <tr><td>admin</td><td>Kullanıcı ve operasyon yönetimi</td></tr>
            <tr><td>developer</td><td>Deploy ve runtime işlemleri</td></tr>
            <tr><td>viewer</td><td>Sadece görüntüleme</td></tr>
            </tbody>
        </table>
    </article>
</section>

<section class="module-card form-stack-top">
    <h2>Kullanıcılar</h2>
    <?php if ($users === []): ?>
        <p class="empty-state">Henüz kullanıcı bulunmuyor.</p>
    <?php else: ?>
        <table class="compact-table">
            <thead>
            <tr>
                <th>E-posta</th>
                <th>Rol</th>
                <th>Durum</th>
                <th>Güncellendi</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <?php $isActive = ((bool) ($user['active'] ?? true)) === true; ?>
                <tr>
                    <td><?= $h($user['email'] ?? '') ?></td>
                    <td>
                        <form method="post" action="/users/update-role" class="inline-form">
                            <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                            <input type="hidden" name="user_id" value="<?= $h($user['id'] ?? '') ?>">
                            <select name="role">
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= $h($role) ?>" <?= ((string) ($user['role'] ?? 'viewer')) === (string) $role ? 'selected' : '' ?>><?= $h($role) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="button-secondary">Rolü Kaydet</button>
                        </form>
                    </td>
                    <td><span class="status-pill"><?= $isActive ? 'aktif' : 'pasif' ?></span></td>
                    <td><?= $h($user['updated_at'] ?? '-') ?></td>
                    <td>
                        <form method="post" action="/users/toggle-active" class="inline-form" data-confirm-message="Kullanıcı durumu değiştirilsin mi?">
                            <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                            <input type="hidden" name="user_id" value="<?= $h($user['id'] ?? '') ?>">
                            <button type="submit" class="button-secondary"><?= $isActive ? 'Pasife Al' : 'Aktif Et' ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
<?php
$content = (string) ob_get_clean();
require AILHOST_ROOT . '/resources/views/layout.php';

