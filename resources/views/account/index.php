<?php
declare(strict_types=1);

$user = is_array($user ?? null) ? $user : [];
$h = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

ob_start();
?>
<div class="page-header">
    <div>
        <h1>Hesabım</h1>
        <p>Hesap bilgileri ve şifre güvenliği ayarlarını yönetin.</p>
    </div>
</div>

<section class="split-grid form-stack-top">
    <article class="module-card">
        <h2>Hesap Özeti</h2>
        <table class="compact-table">
            <tbody>
            <tr><th>E-posta</th><td><?= $h($user['email'] ?? '-') ?></td></tr>
            <tr><th>Rol</th><td><?= $h($user['role'] ?? '-') ?></td></tr>
            <tr><th>Durum</th><td><?= ((bool) ($user['active'] ?? true)) ? 'aktif' : 'pasif' ?></td></tr>
            <tr><th>Son Giriş</th><td><?= $h($user['last_login_at'] ?? '-') ?></td></tr>
            <tr><th>Son Çıkış</th><td><?= $h($user['last_logout_at'] ?? '-') ?></td></tr>
            </tbody>
        </table>
    </article>
    <article class="module-card">
        <h2>Şifre Değiştir</h2>
        <form method="post" action="/account/change-password" class="form-grid form-stack-top">
            <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
            <div class="form-field-full">
                <label>Mevcut Şifre</label>
                <input type="password" name="current_password" required>
            </div>
            <div>
                <label>Yeni Şifre</label>
                <input type="password" name="new_password" minlength="8" required>
            </div>
            <div>
                <label>Yeni Şifre (Tekrar)</label>
                <input type="password" name="new_password_confirm" minlength="8" required>
            </div>
            <div class="form-actions form-field-full">
                <button type="submit">Şifreyi Güncelle</button>
            </div>
        </form>
    </article>
</section>
<?php
$content = (string) ob_get_clean();
require AILHOST_ROOT . '/resources/views/layout.php';

