<?php
declare(strict_types=1);

ob_start();
?>
<section>
    <div class="setup-hero setup-form auth-login-hero">
    <h1>Ailpanel</h1>
    <p class="setup-copy">Devam etmek için e-posta adresinizi ve şifrenizi girin.</p>
    <form method="post" action="/login" class="auth-login-form">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <div class="auth-login-fields">
        <label class="setup-field">
            <span>E-POSTA</span>
            <input type="email" name="email" required autocomplete="email" placeholder="admin@ailpanel.com" value="<?= htmlspecialchars((string) ($emailValue ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </label>
        <label class="setup-field">
            <span>ŞİFRE</span>
            <input type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
            <button class="setup-eye-toggle" type="button" data-password-toggle aria-label="Şifreyi göster">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s4-6 10-6 10 6 10 6-4 6-10 6S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
        </label>
        </div>
        <div class="auth-login-row">
            <label class="auth-login-check"><input type="checkbox" name="remember_me" value="1"><span>Beni Hatırla</span></label>
            <a class="auth-login-link" href="/forgot-password">Şifremi Unuttum</a>
        </div>
        <div>
            <button class="setup-pill setup-pill-gray" type="submit" data-login-submit disabled>
                <span>Giriş Yap</span>
                <span class="setup-pill-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg>
                </span>
            </button>
        </div>
    </form>
    </div>
</section>
<script>
(() => {
    const button = document.querySelector('[data-password-toggle]');
    const input = document.querySelector('input[name="password"]');
    if (!button || !input) return;
    button.addEventListener('click', () => {
        const nextType = input.getAttribute('type') === 'password' ? 'text' : 'password';
        input.setAttribute('type', nextType);
        button.setAttribute('aria-label', nextType === 'password' ? 'Şifreyi göster' : 'Şifreyi gizle');
    });
})();

(() => {
    const form = document.querySelector('form[action="/login"]');
    const submit = form?.querySelector('[data-login-submit]');
    if (!form || !submit) return;
    const requiredFields = Array.from(form.querySelectorAll('input[required]'));
    const sync = () => {
        const valid = requiredFields.every((field) => field.checkValidity());
        submit.disabled = !valid;
        submit.classList.toggle('setup-pill-green', valid);
        submit.classList.toggle('setup-pill-gray', !valid);
    };
    requiredFields.forEach((field) => {
        field.addEventListener('input', sync);
        field.addEventListener('change', sync);
    });
    sync();
})();
</script>
<?php
$content = (string) ob_get_clean();
$toastMessage = (string) ($toastMessage ?? '');
$toastType = (string) ($toastType ?? 'info');
$toastPersistent = (bool) ($toastPersistent ?? false);
$layoutMode = 'auth';
require AILHOST_ROOT . '/resources/views/layout.php';
