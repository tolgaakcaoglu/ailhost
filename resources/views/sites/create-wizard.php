<?php
declare(strict_types=1);

$step = (string) ($step ?? '1');
$wizard = is_array($wizard ?? null) ? $wizard : [];
$h = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$domain = (string) ($wizard['domain'] ?? '');
$runtime = (string) ($wizard['runtime'] ?? 'php');
$webServer = (string) ($wizard['web_server'] ?? 'nginx');
$defaultRoot = $domain !== '' ? '/var/www/' . str_replace('.', '_', $domain) . '/public_html' : '/var/www/example_com/public_html';
$documentRoot = (string) ($wizard['document_root'] ?? $defaultRoot);
$provisionDns = ((bool) ($wizard['provision_dns'] ?? true)) === true;
$provisionSsl = ((bool) ($wizard['provision_ssl'] ?? true)) === true;

ob_start();
?>
<div class="page-header">
    <div>
        <h1>Website Oluşturma Sihirbazı</h1>
        <p>Adımları tamamlayın, sistem tek payload ile site oluşturma işini başlatsın.</p>
    </div>
    <a href="/sites" class="status-pill" style="text-decoration:none;">Listeye Dön</a>
</div>

<div class="steps form-stack-top">
    <span class="step <?= in_array($step, ['1','2','3','4'], true) ? 'is-done' : '' ?>">1. Domain</span>
    <span class="step <?= in_array($step, ['2','3','4'], true) ? 'is-done' : '' ?>">2. Runtime</span>
    <span class="step <?= in_array($step, ['3','4'], true) ? 'is-done' : '' ?>">3. Root & Provision</span>
    <span class="step <?= $step === '4' ? 'is-done' : '' ?>">4. Onay</span>
</div>

<?php if ($step === '1'): ?>
    <section class="module-card form-stack-top">
        <h2>Domain</h2>
        <form method="post" action="/sites/create-wizard" class="form-grid form-stack-top">
            <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
            <input type="hidden" name="step" value="1">
            <div class="form-field-full">
                <label>Alan Adı</label>
                <input type="text" name="domain" required value="<?= $h($domain) ?>" placeholder="example.com">
            </div>
            <div class="form-actions form-field-full"><button type="submit">Devam Et</button></div>
        </form>
    </section>
<?php elseif ($step === '2'): ?>
    <section class="module-card form-stack-top">
        <h2>Runtime ve Web Sunucusu</h2>
        <form method="post" action="/sites/create-wizard" class="form-grid form-stack-top">
            <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
            <input type="hidden" name="step" value="2">
            <div>
                <label>Runtime</label>
                <select name="runtime">
                    <option value="php" <?= $runtime === 'php' ? 'selected' : '' ?>>PHP</option>
                    <option value="node" <?= $runtime === 'node' ? 'selected' : '' ?>>Node</option>
                </select>
            </div>
            <div>
                <label>Web Sunucusu</label>
                <select name="web_server">
                    <option value="nginx" <?= $webServer === 'nginx' ? 'selected' : '' ?>>Nginx</option>
                    <option value="openlitespeed" <?= $webServer === 'openlitespeed' ? 'selected' : '' ?>>OpenLiteSpeed</option>
                </select>
            </div>
            <div class="form-actions form-field-full"><button type="submit">Devam Et</button></div>
        </form>
    </section>
<?php elseif ($step === '3'): ?>
    <section class="module-card form-stack-top">
        <h2>Document Root ve Provision</h2>
        <form method="post" action="/sites/create-wizard" class="form-grid form-stack-top">
            <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
            <input type="hidden" name="step" value="3">
            <div class="form-field-full">
                <label>Document Root</label>
                <input type="text" name="document_root" required value="<?= $h($documentRoot) ?>">
            </div>
            <div>
                <label>DNS Provision</label>
                <select name="provision_dns">
                    <option value="1" <?= $provisionDns ? 'selected' : '' ?>>Açık</option>
                    <option value="0" <?= !$provisionDns ? 'selected' : '' ?>>Kapalı</option>
                </select>
            </div>
            <div>
                <label>SSL Provision</label>
                <select name="provision_ssl">
                    <option value="1" <?= $provisionSsl ? 'selected' : '' ?>>Açık</option>
                    <option value="0" <?= !$provisionSsl ? 'selected' : '' ?>>Kapalı</option>
                </select>
            </div>
            <div class="form-actions form-field-full"><button type="submit">Devam Et</button></div>
        </form>
    </section>
<?php else: ?>
    <section class="module-card form-stack-top">
        <h2>Onay</h2>
        <table class="compact-table">
            <tbody>
            <tr><th>Domain</th><td><?= $h($domain) ?></td></tr>
            <tr><th>Runtime</th><td><?= $h($runtime) ?></td></tr>
            <tr><th>Web Sunucusu</th><td><?= $h($webServer) ?></td></tr>
            <tr><th>Document Root</th><td><?= $h($documentRoot) ?></td></tr>
            <tr><th>DNS Provision</th><td><?= $provisionDns ? 'açık' : 'kapalı' ?></td></tr>
            <tr><th>SSL Provision</th><td><?= $provisionSsl ? 'açık' : 'kapalı' ?></td></tr>
            </tbody>
        </table>
        <form method="post" action="/sites/create-wizard" class="form-stack-top">
            <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
            <input type="hidden" name="step" value="4">
            <div class="form-actions"><button type="submit">Website Oluştur</button></div>
        </form>
    </section>
<?php endif; ?>
<?php
$content = (string) ob_get_clean();
require AILHOST_ROOT . '/resources/views/layout.php';

