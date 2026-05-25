<?php
declare(strict_types=1);

ob_start();
$jobStatusCounts = is_array($jobStatusCounts ?? null) ? $jobStatusCounts : [];
$recentActivities = is_array($recentActivities ?? null) ? $recentActivities : [];
$systemMetrics = is_array($systemMetrics ?? null) ? $systemMetrics : [];
$serviceHealthRows = is_array($serviceHealthRows ?? null) ? $serviceHealthRows : [];

$cpu = is_array($systemMetrics['cpu'] ?? null) ? $systemMetrics['cpu'] : [];
$memory = is_array($systemMetrics['memory'] ?? null) ? $systemMetrics['memory'] : [];
$disk = is_array($systemMetrics['disk'] ?? null) ? $systemMetrics['disk'] : [];
$identity = is_array($systemMetrics['identity'] ?? null) ? $systemMetrics['identity'] : [];

$cpuPercent = (int) ($cpu['percent'] ?? 0);
$ramUsed = (string) ($memory['used'] ?? '0');
$ramTotal = (string) ($memory['total'] ?? '0');
$diskUsed = (string) ($disk['used'] ?? '0');
$diskTotal = (string) ($disk['total'] ?? '0');
$ramPercent = (int) ($memory['percent'] ?? 0);
$diskPercent = (int) ($disk['percent'] ?? 0);
$serverName = (string) ($identity['name'] ?? 'AIL-SERVER');
$serverIp = (string) ($identity['ip'] ?? '127.0.0.1');
$serverOs = (string) ($identity['os'] ?? '-');
$serverUptime = (string) ($identity['uptime'] ?? '-');
$serverLocation = (string) ($identity['location'] ?? 'UTC');
$primarySiteId = (string) ($primarySiteId ?? '');
$siteTarget = $primarySiteId !== '' ? '?site=' . urlencode($primarySiteId) : '';
$csrfToken = (string) ($csrfToken ?? '');
$h = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$metricState = static function (int $percent): string {
    if ($percent >= 90) {
        return 'metric-state-critical';
    }
    if ($percent >= 70) {
        return 'metric-state-busy';
    }
    return 'metric-state-normal';
};

$serviceLabels = [
    'nginx' => 'Nginx',
    'php-fpm' => 'PHP-FPM',
    'mariadb' => 'MariaDB',
    'docker' => 'Docker',
];
$serviceDescriptions = [
    'nginx' => 'Web Server',
    'php-fpm' => 'PHP Engine',
    'mariadb' => 'Database',
    'docker' => 'Container Runtime',
];
$visibleServices = [];
foreach ($serviceHealthRows as $row) {
    if (!is_array($row)) {
        continue;
    }
    $service = (string) ($row['service'] ?? '');
    if (!isset($serviceLabels[$service])) {
        continue;
    }
    $visibleServices[] = $row;
}
$visibleServices = array_slice($visibleServices, 0, 4);
?>

<header class="admin-header">
    <div>
        <p class="admin-crumb"> / ›  GENEL BAKIŞ</p>
        <h1 class="admin-title">Sunucu Durumu</h1>
        <p class="admin-subtitle">Sistem kaynakları ve aktif servislerin güncel durumu.</p>
        <p style="display:none">Hızlı Başlangıç</p>
        <a style="display:none" href="<?= '/sites/deploy' . $siteTarget ?>">Deploy Kısa Yol</a>
    </div>
    <a class="admin-cta" href="/sites/create-wizard">Yeni Website Oluştur</a>
</header>

<section class="admin-grid-3">
    <article class="metric-card <?= $metricState($cpuPercent) ?>" data-live-metric="cpu">
        <div class="metric-card-head">
            <h3>CPU KULLANIMI</h3>
            <span class="metric-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14a8 8 0 1 1 16 0"/><path d="M12 14l4-4"/><path d="M8 14h8"/></svg>
            </span>
        </div>
        <strong data-metric-value>%<?= $cpuPercent ?></strong>
        <div class="metric-bar"><span data-metric-bar style="width: <?= $cpuPercent ?>%"></span></div>
        <p data-metric-caption><?= $h((string) ($cpu['caption'] ?? 'CPU bilgisi alınamadı')) ?></p>
    </article>
    <article class="metric-card <?= $metricState($ramPercent) ?>" data-live-metric="memory">
        <div class="metric-card-head">
            <h3>RAM KULLANIMI</h3>
            <span class="metric-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19V5"/><path d="M8 19V9"/><path d="M12 19V7"/><path d="M16 19v-5"/><path d="M20 19V11"/></svg>
            </span>
        </div>
        <strong data-metric-value><?= $ramUsed ?> / <?= $ramTotal ?>GB</strong>
        <div class="metric-bar"><span data-metric-bar style="width: <?= $ramPercent ?>%"></span></div>
        <p data-metric-caption><?= $h((string) ($memory['caption'] ?? ('%' . $ramPercent . ' Kullanım Oranı'))) ?></p>
    </article>
    <article class="metric-card <?= $metricState($diskPercent) ?>" data-live-metric="disk">
        <div class="metric-card-head">
            <h3>DİSK ALANI</h3>
            <span class="metric-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="5" width="16" height="14" rx="2"/><path d="M8 9h8"/><path d="M8 13h5"/></svg>
            </span>
        </div>
        <strong data-metric-value><?= $diskUsed ?> / <?= $diskTotal ?>GB</strong>
        <div class="metric-bar"><span data-metric-bar style="width: <?= $diskPercent ?>%"></span></div>
        <p data-metric-caption><?= $h((string) ($disk['caption'] ?? 'Disk bilgisi alınamadı')) ?></p>
    </article>
</section>

<section class="split-main">
    <section class="app-panel service-overflow-panel">
        <div class="service-panel-head">
            <h2>Aktif Servisler</h2>
            <a href="/services">Yönet</a>
        </div>
        <div class="service-grid">
            <?php if ($visibleServices === []): ?>
                <div class="service-item">
                    <div>
                        <strong><span class="service-dot off"></span>Servis bilgisi</strong>
                        <p>Durum alınamadı</p>
                    </div>
                    <span class="service-actions"></span>
                </div>
            <?php endif; ?>
            <?php foreach ($visibleServices as $serviceRow): ?>
                <?php
                $service = (string) ($serviceRow['service'] ?? '');
                $status = (string) ($serviceRow['status'] ?? 'unknown');
                $version = trim((string) ($serviceRow['version'] ?? ''));
                $isInstalled = $status !== 'not_installed';
                $isRunning = in_array($status, ['running', 'active', 'ok'], true);
                $dotClass = match (true) {
                    $isRunning => '',
                    in_array($status, ['not_installed', 'disabled'], true) => 'off',
                    default => 'error',
                };
                $description = (string) ($serviceDescriptions[$service] ?? 'Servis');
                if ($version !== '' && $version !== '-') {
                    $description .= ' ' . $version;
                } elseif ($status === 'not_installed') {
                    $description .= ' (Yüklü değil)';
                } elseif ($status !== 'running') {
                    $description .= ' (Durduruldu)';
                }
                ?>
                <div class="service-item">
                    <div>
                        <strong><span class="service-dot <?= $dotClass ?>"></span><?= $h((string) ($serviceLabels[$service] ?? $service)) ?></strong>
                        <p><?= $h($description) ?></p>
                    </div>
                    <div class="service-actions">
                        <button type="button" class="service-menu-button" data-popover-toggle aria-label="<?= $h((string) ($serviceLabels[$service] ?? $service)) ?> aksiyonları">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 12h.01M12 5h.01M12 19h.01"/></svg>
                        </button>
                        <div class="service-popover" data-popover-menu>
                            <a href="/services">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h10"/></svg>
                                Yönet
                            </a>
                            <a href="/logs?source=job">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 5h14v14H5z"/><path d="M8 9h8M8 13h8M8 17h5"/></svg>
                                Log incele
                            </a>
                            <hr>
                            <?php if (!$isInstalled): ?>
                                <form method="post" action="/services/install">
                                    <input type="hidden" name="_csrf" value="<?= $h($csrfToken) ?>">
                                    <input type="hidden" name="service" value="<?= $h($service) ?>">
                                    <input type="hidden" name="redirect_to" value="/dashboard">
                                    <button type="submit">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
                                        Yükle
                                    </button>
                                </form>
                            <?php else: ?>
                                <?php foreach ([['restart', 'Yeniden başlat'], [$isRunning ? 'stop' : 'start', $isRunning ? 'Durdur' : 'Başlat']] as $action): ?>
                                    <form method="post" action="/services/control">
                                        <input type="hidden" name="_csrf" value="<?= $h($csrfToken) ?>">
                                        <input type="hidden" name="service" value="<?= $h($service) ?>">
                                        <input type="hidden" name="action_type" value="<?= $h($action[0]) ?>">
                                        <input type="hidden" name="redirect_to" value="/dashboard">
                                        <button type="submit">
                                            <?php if ($action[0] === 'start'): ?>
                                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.5v13l10-6.5z"/></svg>
                                            <?php elseif ($action[0] === 'stop'): ?>
                                                <svg viewBox="0 0 24 24" fill="currentColor"><rect x="7" y="7" width="10" height="10" rx="1.5"/></svg>
                                            <?php else: ?>
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12a8 8 0 0 1 14-5"/><path d="M18 3v4h-4"/><path d="M20 12a8 8 0 0 1-14 5"/><path d="M6 21v-4h4"/></svg>
                                            <?php endif; ?>
                                            <?= $h($action[1]) ?>
                                        </button>
                                    </form>
                                <?php endforeach; ?>
                                <hr>
                                <form method="post" action="/services/remove" data-confirm-message="<?= $h((string) ($serviceLabels[$service] ?? $service)) ?> sistemden tamamen kaldırılacak. Bu işlem bağlı siteleri etkileyebilir. Devam edilsin mi?">
                                    <input type="hidden" name="_csrf" value="<?= $h($csrfToken) ?>">
                                    <input type="hidden" name="service" value="<?= $h($service) ?>">
                                    <input type="hidden" name="redirect_to" value="/dashboard">
                                    <button type="submit" class="is-danger">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"/><path d="M10 11v6M14 11v6"/><path d="M6 7l1 14h10l1-14"/><path d="M9 7V4h6v3"/></svg>
                                        Kaldır
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <aside class="identity-card">
        <div>
            <small>SUNUCU KİMLİĞİ</small>
            <h3><?= $h($serverName) ?></h3>
        </div>
        <div class="identity-list">
            <p><span>IP</span><b><?= $h($serverIp) ?></b></p>
            <p><span>İşletim sistemi</span><b><?= $h($serverOs) ?></b></p>
            <p><span>Uptime</span><b><?= $h($serverUptime) ?></b></p>
            <p><span>Zaman dilimi</span><b><?= $h($serverLocation) ?></b></p>
        </div>
    </aside>
</section>

<section class="quick-task-panel">
    <div class="quick-task-row" aria-label="Hızlı görevler">
        <a href="/sites/create-wizard">Website oluştur</a>
        <a href="/sites">Website yönet</a>
        <a href="<?= '/sites/files' . $siteTarget ?>">Dosya yükle</a>
        <a href="<?= '/sites/backups' . $siteTarget ?>">Backup al</a>
        <a href="<?= '/sites/deploy' . $siteTarget ?>">Deploy et</a>
    </div>
</section>

<section class="app-panel table-panel">
    <div class="service-panel-head">
        <h2>Son İşlemler</h2>
        <a href="/logs">Listeyi Yenile</a>
    </div>
    <table>
        <thead>
        <tr>
            <th>ZAMAN</th>
            <th>İŞLEM</th>
            <th>KULLANICI</th>
            <th>SONUÇ</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($recentActivities === []): ?>
            <tr>
                <td><?= htmlspecialchars(date('d.m.Y H:i'), ENT_QUOTES, 'UTF-8') ?></td>
                <td>Henüz kayıt bulunmuyor</td>
                <td>Sistem (Auto)</td>
                <td><span class="status-badge run">Devam ediyor</span></td>
            </tr>
        <?php else: ?>
            <?php foreach (array_slice($recentActivities, 0, 4) as $index => $activity): ?>
                <?php
                $action = (string) ($activity['action'] ?? 'İşlem');
                $resource = (string) ($activity['resource_id'] ?? '');
                $resultClass = $index === 3 ? 'err' : ($index === 1 ? 'run' : 'ok');
                $resultText = $index === 3 ? 'Hata' : ($index === 1 ? 'Devam ediyor' : 'Başarılı');
                $logHref = '/logs?source=activity';
                ?>
                <tr>
                    <td><?= htmlspecialchars(str_replace('T', ' ', substr((string) ($activity['created_at'] ?? date(DATE_ATOM)), 0, 16)), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($action . ($resource !== '' ? ' - ' . $resource : ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($activity['actor_email'] ?? 'Sistem (Auto)'), ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?php if ($resultClass === 'err'): ?>
                            <a class="status-badge status-badge-link err" href="<?= $logHref ?>">
                                <?= $resultText ?>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1"/></svg>
                            </a>
                        <?php else: ?>
                            <span class="status-badge <?= $resultClass ?>"><?= $resultText ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</section>

<?php
$content = (string) ob_get_clean();
$pageScripts = <<<'HTML'
<script>
(() => {
    const cards = new Map(Array.from(document.querySelectorAll('[data-live-metric]')).map((card) => [card.dataset.liveMetric, card]));
    if (cards.size === 0) {
        console.log('Live metric card bulunamadı.');
        return;
    }

    console.log('Live metric kartları:', cards);

    let inFlight = false;
    let stopped = false;

    const stateClass = (percent) => {
        if (percent >= 90) return 'metric-state-critical';
        if (percent >= 70) return 'metric-state-busy';
        return 'metric-state-normal';
    };

    const textFor = (key, metric) => {
        if (key === 'cpu') return '%' + String(metric.percent ?? 0);
        return String(metric.used ?? '0') + ' / ' + String(metric.total ?? '0') + 'GB';
    };

    const updateCard = (key, metric) => {
        const card = cards.get(key);
        if (!card || !metric) return;
        const percent = Math.max(0, Math.min(100, Number(metric.percent ?? 0)));
        const value = card.querySelector('[data-metric-value]');
        const bar = card.querySelector('[data-metric-bar]');
        const caption = card.querySelector('[data-metric-caption]');

        card.classList.remove('metric-state-normal', 'metric-state-busy', 'metric-state-critical');
        card.classList.add(stateClass(percent));
        if (value) value.textContent = textFor(key, metric);
        if (bar) bar.style.width = percent + '%';
        if (caption) caption.textContent = String(metric.caption ?? '');
    };

    const refresh = async () => {
        if (inFlight || stopped || document.hidden) return;
        inFlight = true;
        cards.forEach((card) => card.classList.add('is-refreshing'));
        try {
            const response = await fetch('/api/dashboard/metrics', {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store'
            });
            if (!response.ok) {
                console.log('Metrics API hatası:', response.status);
                return;
            }
            const payload = await response.json();

            if (!payload.ok || !payload.metrics) {
                console.log('Metrics response beklenen formatta değil:', payload);
                return;
            }
            
            const metrics = payload.metrics || {};
            updateCard('cpu', metrics.cpu);
            updateCard('memory', metrics.memory);
            updateCard('disk', metrics.disk);
        } catch (error) {
            console.log('Metrics API hatası:', error);
        } finally {
            console.log('Metrics güncellemesi tamamlandı.');
            cards.forEach((card) => card.classList.remove('is-refreshing'));
            inFlight = false;
        }
    };

    const timer = window.setInterval(refresh, 3000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refresh();
    });
    window.addEventListener('beforeunload', () => {
        stopped = true;
        window.clearInterval(timer);
    });
})();
</script>
HTML;
$layoutMode = 'app';
$navActive = 'dashboard';
require AILHOST_ROOT . '/resources/views/layout.php';
