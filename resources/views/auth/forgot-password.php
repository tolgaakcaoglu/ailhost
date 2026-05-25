<?php
declare(strict_types=1);

ob_start();
?>
<section>
    <div class="setup-hero setup-form auth-login-hero">
        <a href="/login" class="auth-login-link" style="display:inline-flex;align-items:center;gap:8px;margin-bottom:18px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M19 12H5"/><path d="M11 6l-6 6 6 6"/></svg>
            <span>Giriş ekranına geri dön</span>
        </a>
        <h1>Şifreni Sıfırla</h1>
        <p class="setup-copy">Kayıtlı e-posta adresinizi girin. Şifreniz sıfırlandıktan sonra size e-posta gönderilecektir.</p>
        <form method="post" action="/forgot-password" class="auth-login-form">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <div class="auth-login-fields">
                <label class="setup-field">
                    <span>E-POSTA</span>
                    <input type="email" name="email" required autocomplete="email" placeholder="admin@ailpanel.com">
                </label>
            </div>
            <div>
                <button class="setup-pill setup-pill-gray" type="submit" data-forgot-submit disabled>
                    <span>Şifreyi Sıfırla</span>
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
    const form = document.querySelector('form[action="/forgot-password"]');
    const submit = form?.querySelector('[data-forgot-submit]');
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

