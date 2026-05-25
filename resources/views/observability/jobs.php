<?php
declare(strict_types=1);

ob_start();
$h = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$selectedStatus = (string) ($selectedStatus ?? 'all');
$selectedLimit = (int) ($selectedLimit ?? 100);
$searchQuery = (string) ($searchQuery ?? '');
?>
<h1>İşler</h1>
<p>Background job kuyruğu ve son durumlar.</p>
<section class="guide-panel form-stack-top">
    <h2>Kuyruk Takip Rehberi</h2>
    <p>Kritik işlemler kuyrukta çalışır. Bu ekran, durum doğrulama ve hata analizi için merkezdir.</p>
    <div class="guide-grid">
        <div class="guide-block">
            <h3>Güvenli Sıra</h3>
            <ol class="guide-list">
                <li>Önce running/pending işleri kontrol et.</li>
                <li>Tamamlanan veya hatalı işleri filtrele.</li>
                <li>Hata varsa son log satırlarını incele.</li>
                <li>Gerekirse ilgili modüle dönüp tekrar dene.</li>
            </ol>
        </div>
        <div class="guide-block">
            <h3>Etkiler</h3>
            <ul class="guide-list">
                <li>Deploy, backup, runtime ve DNS işleri burada izlenir.</li>
                <li>Hatalı işler operasyon zincirini kesebilir.</li>
            </ul>
        </div>
        <div class="guide-block">
            <h3>Çakışma</h3>
            <ul class="guide-list">
                <li>Aynı kaynağa paralel çakışan işler kilitlenebilir.</li>
                <li>İş bitmeden yeni işlem başlatmayın.</li>
            </ul>
        </div>
    </div>
</section>

<section class="metric-grid metric-grid-small form-stack-top">
    <div class="module-card"><h3>Aktif İş</h3><p class="metric-value"><?= (int) ($activeCount ?? 0) ?></p></div>
    <div class="module-card"><h3>Hatalı İş</h3><p class="metric-value"><?= (int) ($failedCount ?? 0) ?></p></div>
    <div class="module-card"><h3>Listelenen</h3><p class="metric-value"><?= count($jobs ?? []) ?></p></div>
</section>
<section class="module-card form-stack-top">
    <div class="operation-strip">
        <div>
            <strong>Kuyruk Operasyon Durumu</strong>
            <p class="status-line">Aktif: <?= (int) ($activeCount ?? 0) ?> | Hatalı: <?= (int) ($failedCount ?? 0) ?> | Listelenen: <?= count($jobs ?? []) ?></p>
            <p class="status-line">Hata analizi için “Son Job Logları” bölümünü kullanın.</p>
        </div>
        <a href="/logs?source=job">Job Loglarını Aç</a>
    </div>
</section>
<section class="module-card form-stack-top">
    <form method="get" action="/jobs" class="form-grid">
        <div>
            <label>Durum</label>
            <select name="status">
                <option value="all" <?= $selectedStatus === 'all' ? 'selected' : '' ?>>Tümü</option>
                <option value="pending" <?= $selectedStatus === 'pending' ? 'selected' : '' ?>>Bekliyor</option>
                <option value="running" <?= $selectedStatus === 'running' ? 'selected' : '' ?>>Çalışıyor</option>
                <option value="done" <?= $selectedStatus === 'done' ? 'selected' : '' ?>>Başarılı</option>
                <option value="failed" <?= $selectedStatus === 'failed' ? 'selected' : '' ?>>Hatalı</option>
            </select>
        </div>
        <div>
            <label>Arama</label>
            <input type="text" name="q" value="<?= $h($searchQuery) ?>" placeholder="job id, tip, hata">
        </div>
        <div>
            <label>Satır Limiti</label>
            <select name="limit">
                <option value="100" <?= $selectedLimit === 100 ? 'selected' : '' ?>>100</option>
                <option value="500" <?= $selectedLimit === 500 ? 'selected' : '' ?>>500</option>
                <option value="1000" <?= $selectedLimit === 1000 ? 'selected' : '' ?>>1000</option>
            </select>
        </div>
        <div class="form-actions"><button type="submit">Filtrele</button></div>
    </form>

    <?php if (empty($jobs ?? [])): ?>
        <p class="empty-state">Filtreye uygun iş kaydı bulunmuyor.</p>
    <?php else: ?>
        <table class="compact-table">
            <thead><tr><th>ID</th><th>Tip</th><th>Durum</th><th>Başlangıç</th><th>Bitiş</th><th>Hata</th></tr></thead>
            <tbody>
            <?php foreach (($jobs ?? []) as $job): ?>
                <?php $status = (string) ($job['status'] ?? ''); ?>
                <tr<?= $status === 'failed' ? ' style="font-weight:600;"' : '' ?>>
                    <td><?= $h($job['id'] ?? '') ?></td>
                    <td><?= $h($job['type'] ?? '') ?></td>
                    <td><?= $h($status) ?></td>
                    <td><?= $h($job['started_at'] ?? '') ?></td>
                    <td><?= $h($job['finished_at'] ?? '') ?></td>
                    <td><?= $h($job['error'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<section class="module-card form-stack-top">
    <h2>Son Job Logları</h2>
    <?php if (empty($jobLogs ?? [])): ?>
        <p class="empty-state">Job log kaydı bulunmuyor.</p>
    <?php else: ?>
        <pre class="log-console"><?php foreach (array_slice(($jobLogs ?? []), 0, 120) as $line): ?>[<?= $h($line['time'] ?? '') ?>] <?= $h($line['stream'] ?? '') ?> <?= $h($line['job_id'] ?? '') ?> - <?= $h($line['message'] ?? '') . "\n" ?><?php endforeach; ?></pre>
    <?php endif; ?>
</section>
<?php
$content = (string) ob_get_clean();
$layoutMode = 'app';
$navActive = 'jobs';
require AILHOST_ROOT . '/resources/views/layout.php';
