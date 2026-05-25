<?php
declare(strict_types=1);

$rows = is_array($rows ?? null) ? $rows : [];
$dockerContainers = is_array($dockerContainers ?? null) ? $dockerContainers : [];
$selectedContainer = (string) ($selectedContainer ?? '');
$dockerLogs = is_array($dockerLogs ?? null) ? $dockerLogs : [];
$h = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$runningCount = 0;
$degradedCount = 0;
foreach ($rows as $row) {
    $status = strtolower((string) ($row['status'] ?? ''));
    if (in_array($status, ['running', 'active', 'ok'], true)) {
        $runningCount++;
    } else {
        $degradedCount++;
    }
}
ob_start();
?>
<h1>Servis Sağlığı</h1>
<p>Temel servis durumlarını izleyin ve kontrol aksiyonlarını job kuyruğundan çalıştırın.</p>

<section class="guide-panel form-stack-top">
    <h2>Servis İşlem Rehberi</h2>
    <p>Servis aksiyonları kuyruğa alınır; sonucu İşler ve Loglar ekranından izleyin.</p>
    <div class="guide-grid">
        <div class="guide-block">
            <h3>Güvenli Sıra</h3>
            <ol class="guide-list">
                <li>Servis durumunu kontrol et.</li>
                <li>Gerekli aksiyonu başlat (start/restart/stop).</li>
                <li>İş kaydını Jobs ekranında takip et.</li>
                <li>Gerekirse container logunu incele.</li>
            </ol>
        </div>
        <div class="guide-block">
            <h3>Etkiler</h3>
            <ul class="guide-list">
                <li>İlgili servis çalışma durumu değişebilir.</li>
                <li>Bağlı site operasyonları etkilenebilir.</li>
                <li>Beklenen süre: kısa kuyruk süresi.</li>
            </ul>
        </div>
        <div class="guide-block">
            <h3>Çakışma</h3>
            <ul class="guide-list">
                <li>Aynı servise peş peşe farklı aksiyon göndermeyin.</li>
                <li>Önce önceki işin sonucu görülmelidir.</li>
            </ul>
        </div>
    </div>
</section>

<section class="module-card form-stack-top">
    <div class="operation-strip">
        <div>
            <strong>Servis Operasyon Durumu</strong>
            <p class="status-line">Çalışan servis: <?= $runningCount ?> | Dikkat gerektiren: <?= $degradedCount ?></p>
            <p class="status-line">Kontrol aksiyonları arka planda iş olarak çalışır.</p>
        </div>
        <a href="/jobs?status=running">Aktif İşleri Aç</a>
    </div>
</section>

<section class="module-card form-stack-top">
    <?php if ($rows === []): ?>
        <p class="empty-state">Servis bilgisi bulunamadı.</p>
    <?php else: ?>
        <table class="compact-table">
            <thead><tr><th>Servis</th><th>Durum</th><th>Güncelleme</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= $h($row['service'] ?? '') ?></td>
                    <td><?= $h($row['status'] ?? 'unknown') ?></td>
                    <td><?= $h($row['updated_at'] ?? '-') ?></td>
                    <td>
                        <div class="inline-actions">
                            <form method="post" action="/services/control" class="inline-form">
                                <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                                <input type="hidden" name="service" value="<?= $h($row['service'] ?? '') ?>">
                                <input type="hidden" name="action_type" value="start">
                                <button type="submit">Start</button>
                            </form>
                            <form method="post" action="/services/control" class="inline-form">
                                <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                                <input type="hidden" name="service" value="<?= $h($row['service'] ?? '') ?>">
                                <input type="hidden" name="action_type" value="restart">
                                <button type="submit" class="button-secondary">Restart</button>
                            </form>
                            <form method="post" action="/services/control" class="inline-form">
                                <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                                <input type="hidden" name="service" value="<?= $h($row['service'] ?? '') ?>">
                                <input type="hidden" name="action_type" value="stop">
                                <button type="submit" class="button-secondary">Stop</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<section class="module-card form-stack-top">
    <h2>Docker Image Pull</h2>
    <form method="post" action="/services/docker/pull" class="form-grid">
        <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
        <div>
            <label>Image</label>
            <input type="text" name="image" placeholder="nginx:alpine" required>
        </div>
        <div class="form-actions"><button type="submit">Pull İşini Başlat</button></div>
    </form>
</section>

<section class="module-card form-stack-top">
    <h2>Container Listesi</h2>
    <?php if ($dockerContainers === []): ?>
        <p class="empty-state">Container bulunamadı veya Docker erişimi yok.</p>
    <?php else: ?>
        <table class="compact-table">
            <thead><tr><th>Ad</th><th>Image</th><th>Durum</th><th>Port</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($dockerContainers as $container): ?>
                <?php $name = (string) ($container['name'] ?? ''); ?>
                <tr>
                    <td><?= $h($name) ?></td>
                    <td><?= $h($container['image'] ?? '') ?></td>
                    <td><?= $h($container['status'] ?? '') ?></td>
                    <td><?= $h($container['ports'] ?? '-') ?></td>
                    <td><a href="/services?container=<?= urlencode($name) ?>">Log</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<section class="module-card form-stack-top">
    <h2>Container Logları</h2>
    <?php if ($selectedContainer === ''): ?>
        <p class="empty-state">Log görmek için bir container seçin.</p>
    <?php elseif (($dockerLogs['ok'] ?? false) !== true): ?>
        <p class="empty-state"><?= $h($dockerLogs['message'] ?? 'Container logları alınamadı.') ?></p>
    <?php else: ?>
        <p class="status-line">Container: <?= $h($selectedContainer) ?></p>
        <pre class="log-console"><?= $h((string) ($dockerLogs['logs'] ?? '')) ?></pre>
    <?php endif; ?>
</section>
<?php
$content = (string) ob_get_clean();
require AILHOST_ROOT . '/resources/views/layout.php';
