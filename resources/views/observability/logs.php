<?php
declare(strict_types=1);

ob_start();
$h = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$siteOptions = is_array($siteOptions ?? null) ? $siteOptions : [];
$selectedSite = (string) ($selectedSite ?? 'all');
$selectedType = (string) ($selectedType ?? 'all');
$selectedLimit = (int) ($selectedLimit ?? 100);
$searchQuery = (string) ($searchQuery ?? '');
$selectedSource = (string) ($selectedSource ?? 'activity');
$jobLogs = is_array($jobLogs ?? null) ? $jobLogs : [];
?>
<h1>Loglar</h1>
<p>Aktivite geçmişini filtreleyerek inceleyin.</p>
<section class="module-card form-stack-top">
    <form method="get" action="/logs" class="form-grid">
        <div>
            <label>Kaynak</label>
            <select name="source">
                <option value="activity" <?= $selectedSource === 'activity' ? 'selected' : '' ?>>Aktivite</option>
                <option value="job" <?= $selectedSource === 'job' ? 'selected' : '' ?>>Job Logları</option>
            </select>
        </div>
        <div>
            <label>Site</label>
            <select name="site">
                <option value="all" <?= $selectedSite === 'all' ? 'selected' : '' ?>>Tüm siteler</option>
                <?php foreach ($siteOptions as $site): ?>
                    <option value="<?= $h($site['id'] ?? '') ?>" <?= $selectedSite === (string) ($site['id'] ?? '') ? 'selected' : '' ?>>
                        <?= $h($site['domain'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Log Tipi</label>
            <select name="resource_type">
                <option value="all" <?= $selectedType === 'all' ? 'selected' : '' ?>>Tümü</option>
                <option value="site" <?= $selectedType === 'site' ? 'selected' : '' ?>>Site</option>
                <option value="domain" <?= $selectedType === 'domain' ? 'selected' : '' ?>>Domain</option>
                <option value="dns" <?= $selectedType === 'dns' ? 'selected' : '' ?>>DNS</option>
                <option value="backup" <?= $selectedType === 'backup' ? 'selected' : '' ?>>Backup</option>
                <option value="mail" <?= $selectedType === 'mail' ? 'selected' : '' ?>>Mail</option>
                <option value="file" <?= $selectedType === 'file' ? 'selected' : '' ?>>Dosya</option>
                <option value="security" <?= $selectedType === 'security' ? 'selected' : '' ?>>Güvenlik</option>
                <option value="auth" <?= $selectedType === 'auth' ? 'selected' : '' ?>>Oturum</option>
                <option value="install" <?= $selectedType === 'install' ? 'selected' : '' ?>>Kurulum</option>
            </select>
        </div>
        <div>
            <label>Arama</label>
            <input type="text" name="q" value="<?= $h($searchQuery) ?>" placeholder="işlem, kullanıcı veya kaynak">
        </div>
        <div>
            <label>Satır Limiti</label>
            <select name="limit">
                <option value="100" <?= $selectedLimit === 100 ? 'selected' : '' ?>>100</option>
                <option value="500" <?= $selectedLimit === 500 ? 'selected' : '' ?>>500</option>
                <option value="1000" <?= $selectedLimit === 1000 ? 'selected' : '' ?>>1000</option>
            </select>
        </div>
        <div class="form-actions form-field-full"><button type="submit">Filtrele</button></div>
    </form>
    <div class="inline-actions form-stack-top">
        <a class="status-pill" style="text-decoration:none;" href="/logs/export?format=json&resource_type=<?= urlencode($selectedType) ?>&site=<?= urlencode($selectedSite) ?>&q=<?= urlencode($searchQuery) ?>">JSON Dışa Aktar</a>
        <a class="status-pill" style="text-decoration:none;" href="/logs/export?format=csv&resource_type=<?= urlencode($selectedType) ?>&site=<?= urlencode($selectedSite) ?>&q=<?= urlencode($searchQuery) ?>">CSV Dışa Aktar</a>
        <?php if (!empty($csrfToken ?? '')): ?>
            <form method="post" action="/logs/prune" class="inline-form" data-confirm-message="Seçilen süreden eski audit kayıtları silinsin mi?">
                <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                <select name="days">
                    <option value="30">30 gün</option>
                    <option value="90" selected>90 gün</option>
                    <option value="180">180 gün</option>
                    <option value="365">365 gün</option>
                </select>
                <button type="submit" class="button-secondary">Retention Uygula</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($selectedSource === 'job'): ?>
        <?php if (empty($jobLogs)): ?>
            <p class="empty-state">Filtreye uygun job log kaydı bulunmuyor.</p>
        <?php else: ?>
            <pre class="log-console"><?php foreach ($jobLogs as $line): ?>[<?= $h($line['time'] ?? '') ?>] <?= $h($line['stream'] ?? '') ?> <?= $h($line['job_id'] ?? '') ?> - <?= $h($line['message'] ?? '') . "\n" ?><?php endforeach; ?></pre>
        <?php endif; ?>
    <?php else: ?>
        <?php if (empty($activities ?? [])): ?>
            <p class="empty-state">Filtreye uygun aktivite kaydı bulunmuyor.</p>
        <?php else: ?>
            <table class="compact-table">
                <thead><tr><th>Tarih</th><th>Kullanıcı</th><th>İşlem</th><th>Kaynak</th></tr></thead>
                <tbody>
                <?php foreach (($activities ?? []) as $activity): ?>
                    <?php
                        $action = (string) ($activity['action'] ?? '');
                        $actionLower = strtolower($action);
                        $isWarn = str_contains($actionLower, 'hata')
                            || str_contains($actionLower, 'fail')
                            || str_contains($actionLower, 'sil');
                    ?>
                    <tr<?= $isWarn ? ' style="font-weight:600;"' : '' ?>>
                        <td><?= $h($activity['created_at'] ?? '') ?></td>
                        <td><?= $h($activity['actor_email'] ?? '') ?></td>
                        <td><?= $h($action) ?></td>
                        <td><?= $h($activity['resource_type'] ?? '') ?>:<?= $h($activity['resource_id'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php
$content = (string) ob_get_clean();
$layoutMode = 'app';
$navActive = 'logs';
require AILHOST_ROOT . '/resources/views/layout.php';
