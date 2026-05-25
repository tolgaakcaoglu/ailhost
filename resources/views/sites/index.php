<?php
declare(strict_types=1);

$activeModule = (string) ($activeModule ?? 'list');
$selectedSiteId = (string) (($selectedSite['id'] ?? ''));
$selectedDomains = is_array($selectedDomains ?? null) ? $selectedDomains : [];
$sslStatuses = is_array($sslStatuses ?? null) ? $sslStatuses : [];
$phpProfile = is_array($phpProfile ?? null) ? $phpProfile : [];
$runtimeState = is_array($runtimeState ?? null) ? $runtimeState : [];
$databases = is_array($databases ?? null) ? $databases : [];
$proxyRoutes = is_array($proxyRoutes ?? null) ? $proxyRoutes : [];
$deployProfile = is_array($deployProfile ?? null) ? $deployProfile : [];
$jobLocks = is_array($jobLocks ?? null) ? $jobLocks : [];
$permissions = is_array($permissions ?? null) ? $permissions : [];
$mailboxes = is_array($mailboxes ?? null) ? $mailboxes : [];
$mailDomainRecords = is_array($mailDomainRecords ?? null) ? $mailDomainRecords : [];
$mailQueue = is_array($mailQueue ?? null) ? $mailQueue : [];
$mailDeliveryLogs = is_array($mailDeliveryLogs ?? null) ? $mailDeliveryLogs : [];
$webmailSettings = is_array($webmailSettings ?? null) ? $webmailSettings : [];
$dnsRecords = is_array($dnsRecords ?? null) ? $dnsRecords : [];
$mailHealth = is_array($mailHealth ?? null) ? $mailHealth : [];
$wordpress = is_array($wordpress ?? null) ? $wordpress : [];
$wordpressCliPolicy = is_array($wordpressCliPolicy ?? null) ? $wordpressCliPolicy : [];
$wordpressSecurityReport = is_array($wordpressSecurityReport ?? null) ? $wordpressSecurityReport : [];
$backups = is_array($backups ?? null) ? $backups : [];
$backupSchedule = is_array($backupSchedule ?? null) ? $backupSchedule : [];
$fileListing = is_array($fileListing ?? null) ? $fileListing : [];
$fileUploadMaxMb = (int) ($fileUploadMaxMb ?? 20);
$fileContent = is_array($fileContent ?? null) ? $fileContent : [];
$securityStatus = is_array($securityStatus ?? null) ? $securityStatus : [];
$ftpAccounts = is_array($ftpAccounts ?? null) ? $ftpAccounts : [];
$securityChecklist = is_array($securityChecklist ?? null) ? $securityChecklist : [];
$runtimeLogs = is_array($runtimeLogs ?? null) ? $runtimeLogs : [];
$siteActivities = is_array($siteActivities ?? null) ? $siteActivities : [];
$siteJobStatus = is_array($siteJobStatus ?? null) ? $siteJobStatus : [];
$filteredSites = is_array($filteredSites ?? null) ? $filteredSites : [];
$sites = is_array($sites ?? null) ? $sites : [];
$listSearch = (string) ($listSearch ?? '');
$listStatus = (string) ($listStatus ?? 'all');

$jobStatusByGroup = [];
foreach ($siteJobStatus as $row) {
    if (!is_array($row)) {
        continue;
    }
    $key = (string) ($row['group'] ?? '');
    if ($key === '') {
        continue;
    }
    $jobStatusByGroup[$key] = [
        'status' => (string) ($row['status'] ?? 'Beklenmiyor'),
        'detail' => (string) ($row['detail'] ?? '-'),
    ];
}

$h = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$moduleUrl = static function (string $module, string $siteId = ''): string {
    $base = match ($module) {
        'overview' => '/sites/overview',
        'domains' => '/sites/domains',
        'ssl' => '/sites/ssl',
        'runtime' => '/sites/runtime',
        'proxy' => '/sites/proxy',
        'deploy' => '/sites/deploy',
        'files' => '/sites/files',
        'mail' => '/sites/mail',
        'dns' => '/sites/dns',
        'backups' => '/sites/backups',
        'security' => '/sites/security',
        default => '/sites',
    };
    return $siteId !== '' ? $base . '?site=' . urlencode($siteId) : $base;
};
$modules = [
    'overview' => 'Özet',
    'domains' => 'Domainler',
    'ssl' => 'SSL',
    'runtime' => 'Runtime',
    'proxy' => 'Proxy',
    'deploy' => 'Deploy',
    'files' => 'Dosyalar',
    'mail' => 'Mail',
    'dns' => 'DNS',
    'backups' => 'Backup',
    'security' => 'Güvenlik',
];
$moduleFlowOrder = ['overview', 'domains', 'dns', 'ssl', 'files', 'deploy', 'runtime', 'proxy', 'backups', 'mail', 'security'];
$moduleFlowLabels = [
    'overview' => 'Özet',
    'domains' => 'Domain',
    'dns' => 'DNS',
    'ssl' => 'SSL',
    'files' => 'Dosyalar',
    'deploy' => 'Deploy',
    'runtime' => 'Runtime',
    'proxy' => 'Proxy',
    'backups' => 'Backup',
    'mail' => 'Mail',
    'security' => 'Güvenlik',
];
$role = (string) ($authRole ?? 'viewer');
$permissionHints = [
    'dns_delete' => 'Bu rolde DNS/domain silme yetkisi yok.',
    'site_delete' => 'Bu rolde site silme yetkisi yok.',
    'deploy_run' => 'Bu rolde deploy başlatma yetkisi yok.',
    'runtime_control' => 'Bu rolde runtime kontrol yetkisi yok.',
    'backup_restore' => 'Bu rolde restore işlemi yetkisi yok.',
];
$modulePermissions = [
    'overview' => null,
    'domains' => null,
    'dns' => 'dns_delete',
    'ssl' => null,
    'files' => null,
    'deploy' => 'deploy_run',
    'runtime' => 'runtime_control',
    'proxy' => null,
    'backups' => 'backup_restore',
    'mail' => null,
    'security' => 'site_delete',
];
$restrictedModules = [];
foreach ($moduleFlowOrder as $moduleKey) {
    $perm = $modulePermissions[$moduleKey] ?? null;
    if ($perm !== null && empty($permissions[$perm])) {
        $restrictedModules[] = (string) ($moduleFlowLabels[$moduleKey] ?? $moduleKey);
    }
}
$activeJobLabels = [];
foreach ($siteJobStatus as $row) {
    $status = (string) ($row['status'] ?? '');
    if (in_array($status, ['Bekliyor', 'Çalışıyor'], true)) {
        $activeJobLabels[] = (string) ($row['label'] ?? 'İş');
    }
}
$activeJobSummary = $activeJobLabels === [] ? 'Aktif iş yok' : implode(', ', array_slice($activeJobLabels, 0, 3));
$activeFlowIndex = array_search($activeModule, $moduleFlowOrder, true);
$nextFlowModule = null;
if ($activeFlowIndex !== false) {
    for ($i = $activeFlowIndex + 1; $i < count($moduleFlowOrder); $i++) {
        $candidate = $moduleFlowOrder[$i];
        $perm = $modulePermissions[$candidate] ?? null;
        if ($perm === null || !empty($permissions[$perm])) {
            $nextFlowModule = $candidate;
            break;
        }
    }
}
$flowBusy = $activeJobLabels !== [];
$documentRoot = (string) ($selectedSite['document_root'] ?? ($selectedSite['root_path'] ?? '-'));
$renderGuidePanel = static function (string $title, string $description, array $steps, array $effects, array $conflicts, array $nextActions = []) use ($h): void {
    ?>
    <section class="guide-panel form-stack-top">
        <h2><?= $h($title) ?></h2>
        <p><?= $h($description) ?></p>
        <div class="guide-grid">
            <div class="guide-block">
                <h3>Güvenli Sıra</h3>
                <ol class="guide-list">
                    <?php foreach ($steps as $step): ?><li><?= $h($step) ?></li><?php endforeach; ?>
                </ol>
            </div>
            <div class="guide-block">
                <h3>Etkiler</h3>
                <ul class="guide-list">
                    <?php foreach ($effects as $effect): ?><li><?= $h($effect) ?></li><?php endforeach; ?>
                </ul>
            </div>
            <div class="guide-block">
                <h3>Çakışma Kuralları</h3>
                <ul class="guide-list">
                    <?php foreach ($conflicts as $conflict): ?><li><?= $h($conflict) ?></li><?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php if ($nextActions !== []): ?>
            <div class="next-actions">
                <?php foreach ($nextActions as $action): ?>
                    <a href="<?= $h((string) ($action['href'] ?? '#')) ?>"><?= $h((string) ($action['label'] ?? 'Aç')) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php
};
$renderOperationStatusStrip = static function (string $title, array $summary, array $hints = [], string $jobsHref = '') use ($h): void {
    $status = (string) ($summary['status'] ?? 'Beklenmiyor');
    $detail = (string) ($summary['detail'] ?? '-');
    ?>
    <div class="operation-strip form-stack-top">
        <div>
            <strong><?= $h($title) ?></strong>
            <p class="status-line">Durum: <?= $h($status) ?> | Son kayıt: <?= $h($detail) ?></p>
            <?php foreach ($hints as $hint): ?>
                <p class="status-line"><?= $h((string) $hint) ?></p>
            <?php endforeach; ?>
        </div>
        <?php if ($jobsHref !== ''): ?>
            <a href="<?= $h($jobsHref) ?>">İş geçmişi</a>
        <?php endif; ?>
    </div>
    <?php
};

ob_start();
?>
<div class="page-header">
    <div>
        <h1>Website Yönetimi</h1>
        <p>Operasyonları daha sade akışlarla yönetin.</p>
    </div>
    <div class="quick-create">
        <a href="/sites/create-wizard" class="status-pill" style="text-decoration:none; justify-content:center;">Website Oluştur</a>
    </div>
</div>

<?php if ($activeModule === 'list'): ?>
    <?php
    $totalSitesCount = count($sites);
    $activeSitesCount = count(array_filter($sites, static fn(array $s): bool => (string) ($s['status'] ?? '') === 'active'));
    $provisioningSitesCount = count(array_filter($sites, static fn(array $s): bool => (string) ($s['status'] ?? '') === 'provisioning'));
    $deletingSitesCount = count(array_filter($sites, static fn(array $s): bool => (string) ($s['status'] ?? '') === 'deleting'));
    $hasBusySite = ($provisioningSitesCount + $deletingSitesCount) > 0;
    ?>
    <section class="guide-panel form-stack-top">
        <h2>Site Seçim Rehberi</h2>
        <p>Önce doğru siteyi seçin, sonra ilgili modüle geçin. Site bağlamı değiştiğinde tüm operasyon hedefi değişir.</p>
        <div class="guide-grid">
            <div class="guide-block">
                <h3>Güvenli Sıra</h3>
                <ol class="guide-list">
                    <li>Siteyi domain ve durumuna göre filtrele.</li>
                    <li>Doğru site kartından Yönet aksiyonunu aç.</li>
                    <li>İşleme göre DNS, Dosya, Backup veya Deploy modülüne geç.</li>
                    <li>Aktif işleri Jobs ekranından takip et.</li>
                </ol>
            </div>
            <div class="guide-block">
                <h3>Etkiler</h3>
                <ul class="guide-list">
                    <li>Yanlış site seçimi yanlış ortamda değişikliğe neden olur.</li>
                    <li>Provisioning durumundaki site tam hazır olmayabilir.</li>
                    <li>Deleting durumundaki sitede yeni operasyon önerilmez.</li>
                </ul>
            </div>
            <div class="guide-block">
                <h3>Çakışma Kuralları</h3>
                <ul class="guide-list">
                    <li>Provisioning/silme sırasında kritik değişiklik başlatmayın.</li>
                    <li>Önce aktif işi bitirip sonra yeni operasyon başlatın.</li>
                    <li>Kararsız durumda önce Logs/Jobs ekranını kontrol edin.</li>
                </ul>
            </div>
        </div>
        <div class="next-actions">
            <a href="/sites/create-wizard">Yeni Website Oluştur</a>
            <a href="/jobs">Aktif İşleri Gör</a>
            <a href="/logs">Aktivite Logları</a>
        </div>
    </section>
    <section class="module-card form-stack-top">
        <div class="operation-strip">
            <div>
                <strong>Site Listesi Operasyon Durumu</strong>
                <p class="status-line">Toplam: <?= $totalSitesCount ?> | Aktif: <?= $activeSitesCount ?> | Provisioning: <?= $provisioningSitesCount ?> | Siliniyor: <?= $deletingSitesCount ?></p>
                <p class="status-line"><?= $hasBusySite ? 'Provisioning/silme sürecindeki sitelerde operasyon planını dikkatle uygulayın.' : 'Seçim sonrası modül bazlı operasyona geçebilirsiniz.' ?></p>
            </div>
            <a href="/jobs?status=running">Aktif İşler</a>
        </div>
    </section>
    <section class="module-card form-stack-top">
        <h2>Website Listesi</h2>
        <form method="get" action="/sites" class="form-grid form-stack-top">
            <div>
                <label>Site ara</label>
                <input type="text" name="q" value="<?= $h($listSearch) ?>" placeholder="ornek-test-01.com">
            </div>
            <div>
                <label>Durum</label>
                <select name="status">
                    <option value="all" <?= $listStatus === 'all' ? 'selected' : '' ?>>Tümü</option>
                    <option value="active" <?= $listStatus === 'active' ? 'selected' : '' ?>>Aktif</option>
                    <option value="provisioning" <?= $listStatus === 'provisioning' ? 'selected' : '' ?>>Provisioning</option>
                    <option value="deleting" <?= $listStatus === 'deleting' ? 'selected' : '' ?>>Siliniyor</option>
                </select>
            </div>
            <div class="form-actions"><button type="submit" class="button-secondary">Filtrele</button></div>
        </form>
        <?php if (empty($filteredSites)): ?>
            <p class="empty-state">Henüz website kaydı bulunmuyor. İlk website kaydını oluşturun.</p>
        <?php else: ?>
            <div class="site-cards">
                <?php foreach ($filteredSites as $site): ?>
                    <?php $siteId = (string) ($site['id'] ?? ''); ?>
                    <article class="site-card">
                        <div class="site-card-top">
                            <h3><?= $h($site['domain'] ?? '') ?></h3>
                            <span class="status-pill"><?= $h($site['status'] ?? '') ?></span>
                        </div>
                        <p>IP: <?= $h($site['server_ip'] ?? '-') ?></p>
                        <div class="site-card-actions">
                            <a href="<?= $h($moduleUrl('overview', $siteId)) ?>">Yönet</a>
                            <a href="<?= $h($moduleUrl('dns', $siteId)) ?>">DNS</a>
                            <a href="<?= $h($moduleUrl('backups', $siteId)) ?>">Backup</a>
                            <a href="<?= $h($moduleUrl('files', $siteId)) ?>">Dosyalar</a>
                            <a href="<?= $h(!empty($permissions['deploy_run']) ? $moduleUrl('deploy', $siteId) : $moduleUrl('overview', $siteId)) ?>">Deploy</a>
                        </div>
                        <?php if (empty($permissions['deploy_run'])): ?>
                            <p class="field-hint">Bu rolde deploy başlatma yetkisi yok.</p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php else: ?>
    <section class="module-card form-stack-top">
        <div class="site-context-header">
            <div>
                <h2><?= $h($selectedSite['domain'] ?? 'Website') ?></h2>
                <p>Seçili site bağlamı. İşlemler bu site üzerinde çalışır.</p>
            </div>
            <form method="get" action="<?= $h($moduleUrl($activeModule)) ?>" class="site-switcher">
                <label for="siteSelect">Site</label>
                <select id="siteSelect" name="site" onchange="this.form.submit()">
                    <?php foreach (($sites ?? []) as $site): ?>
                        <?php $siteId = (string) ($site['id'] ?? ''); ?>
                        <option value="<?= $h($siteId) ?>" <?= $siteId === $selectedSiteId ? 'selected' : '' ?>>
                            <?= $h($site['domain'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <div class="context-meta">
            <div class="context-meta-item"><span>Durum</span><strong><?= $h($selectedSite['status'] ?? '-') ?></strong></div>
            <div class="context-meta-item"><span>Sunucu IP</span><strong><?= $h($selectedSite['server_ip'] ?? '-') ?></strong></div>
            <div class="context-meta-item"><span>Document root</span><strong><?= $h($documentRoot) ?></strong></div>
            <div class="context-meta-item"><span>Aktif iş</span><strong><?= $h($activeJobSummary) ?></strong></div>
        </div>
        <nav class="module-tabs">
            <?php foreach ($modules as $key => $label): ?>
                <a href="<?= $h($moduleUrl($key, $selectedSiteId)) ?>" class="<?= $activeModule === $key ? 'is-active' : '' ?>"><?= $h($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </section>
    <section class="module-card form-stack-top">
        <div class="operation-strip">
            <div>
                <strong>Modül İş Sırası</strong>
                <p class="status-line">Rol: <?= $h($role) ?></p>
                <p class="status-line">Önerilen sıra: Özet -> Domain -> DNS -> SSL -> Dosyalar -> Deploy -> Runtime -> Proxy -> Backup -> Mail -> Güvenlik</p>
                <p class="status-line">
                    Şu an: <?= $h((string) ($moduleFlowLabels[$activeModule] ?? $activeModule)) ?>
                    <?php if ($nextFlowModule !== null): ?>
                        | Sonraki öneri: <?= $h((string) ($moduleFlowLabels[$nextFlowModule] ?? $nextFlowModule)) ?>
                    <?php else: ?>
                        | Sonraki öneri: Akış tamamlandı
                    <?php endif; ?>
                </p>
                <p class="status-line"><?= $flowBusy ? 'Aktif iş var: yeni adım öncesi Jobs ekranından sonucu doğrulayın.' : 'Aktif çakışan iş görünmüyor; bir sonraki adıma geçebilirsiniz.' ?></p>
                <?php if ($restrictedModules !== []): ?>
                    <p class="status-line">Bu rolde kısıtlı alanlar: <?= $h(implode(', ', $restrictedModules)) ?></p>
                <?php endif; ?>
            </div>
            <?php if ($nextFlowModule !== null): ?>
                <a href="<?= $h($moduleUrl($nextFlowModule, $selectedSiteId)) ?>">Sonraki Modüle Git</a>
            <?php else: ?>
                <a href="/jobs?q=<?= urlencode($selectedSiteId) ?>">İşleri Aç</a>
            <?php endif; ?>
        </div>
    </section>
    <section class="module-card form-stack-top">
        <div class="operation-strip">
            <div>
                <strong>İşlem Durumu</strong>
                <p class="status-line">Bekleyen, çalışan ve hatalı işleri buradan takip edin.</p>
            </div>
            <a href="/jobs?q=<?= urlencode($selectedSiteId) ?>">İş geçmişi</a>
        </div>
        <?php if ($siteJobStatus === []): ?>
            <p class="empty-state">Bu site için iş durumu kaydı bulunmuyor.</p>
        <?php else: ?>
            <table class="compact-table">
                <thead><tr><th>Operasyon</th><th>Durum</th><th>Son Kayıt</th></tr></thead>
                <tbody>
                <?php foreach ($siteJobStatus as $row): ?>
                    <tr>
                        <td><?= $h((string) ($row['label'] ?? '')) ?></td>
                        <td><?= $h((string) ($row['status'] ?? 'Beklenmiyor')) ?></td>
                        <td><?= $h((string) ($row['detail'] ?? '-')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <?php if ($activeModule === 'overview'): ?>
        <section class="metric-grid form-stack-top">
            <div class="module-card"><h3>Domain</h3><p class="metric-value"><?= count($selectedDomains) ?></p></div>
            <div class="module-card"><h3>Veritabanı</h3><p class="metric-value"><?= count($databases) ?></p></div>
            <div class="module-card"><h3>Mailbox</h3><p class="metric-value"><?= count($mailboxes) ?></p></div>
            <div class="module-card"><h3>Backup</h3><p class="metric-value"><?= count($backups) ?></p></div>
        </section>
        <section class="module-card form-stack-top">
            <h2>Son Aktiviteler</h2>
            <?php if (empty($siteActivities)): ?>
                <p class="empty-state">Bu site için aktivite kaydı bulunmuyor.</p>
            <?php else: ?>
                <table class="compact-table">
                    <thead><tr><th>Tarih</th><th>Kullanıcı</th><th>İşlem</th></tr></thead>
                    <tbody>
                    <?php foreach ($siteActivities as $activity): ?>
                        <tr>
                            <td><?= $h($activity['created_at'] ?? '') ?></td>
                            <td><?= $h($activity['actor_email'] ?? '') ?></td>
                            <td><?= $h($activity['action'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <p class="form-stack-top"><a href="/logs?source=activity&site=<?= urlencode($selectedSiteId) ?>">Tüm kayıtları Loglar ekranında aç</a></p>
        </section>
    <?php elseif ($activeModule === 'domains'): ?>
        <section class="module-card form-stack-top">
            <h2>Domainler</h2>
            <table class="compact-table">
                <thead><tr><th>Domain</th><th>Tip</th><th>SSL</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($selectedDomains as $domain): ?>
                    <tr>
                        <td><?= $h($domain['domain'] ?? '') ?></td>
                        <td><?= $h($domain['type'] ?? '') ?></td>
                        <td><?= $h($sslStatuses[(string) ($domain['domain'] ?? '')] ?? 'bekleniyor') ?></td>
                        <td>
                            <?php if (($domain['type'] ?? '') !== 'primary'): ?>
                                <form method="post" action="/sites/domain/delete" class="inline-form" data-confirm-message="Domain silinsin mi?">
                                    <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                                    <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                                    <input type="hidden" name="domain_id" value="<?= $h($domain['id'] ?? '') ?>">
                                    <button type="submit" class="button-secondary" <?= empty($permissions['dns_delete']) ? 'disabled' : '' ?>>Sil</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (empty($permissions['dns_delete'])): ?>
                <p class="field-hint form-stack-top"><?= $h((string) ($permissionHints['dns_delete'] ?? '')) ?></p>
            <?php endif; ?>
            <div class="form-actions"><button type="button" data-open-drawer="drawerDomainAdd">Domain Ekle</button></div>
        </section>
    <?php elseif ($activeModule === 'ssl'): ?>
        <section class="module-card form-stack-top">
            <h2>SSL Durumu</h2>
            <table class="compact-table">
                <thead><tr><th>Domain</th><th>Sertifika</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($selectedDomains as $domain): ?>
                    <?php $domainName = (string) ($domain['domain'] ?? ''); ?>
                    <tr>
                        <td><?= $h($domainName) ?></td>
                        <td><?= $h($sslStatuses[$domainName] ?? 'bekleniyor') ?></td>
                        <td>
                            <div class="inline-actions">
                                <form method="post" action="/sites/ssl/action" class="inline-form">
                                    <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                                    <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                                    <input type="hidden" name="domain" value="<?= $h($domainName) ?>">
                                    <input type="hidden" name="action_type" value="issue">
                                    <button type="submit">Issue</button>
                                </form>
                                <form method="post" action="/sites/ssl/action" class="inline-form">
                                    <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                                    <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                                    <input type="hidden" name="domain" value="<?= $h($domainName) ?>">
                                    <input type="hidden" name="action_type" value="renew">
                                    <button type="submit" class="button-secondary">Renew</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
        <section class="module-card form-stack-top">
            <h2>HTTPS Zorlama</h2>
            <form method="post" action="/sites/ssl/force-https" class="form-grid">
                <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                <div>
                    <label>Force HTTPS</label>
                    <select name="force_https">
                        <option value="1" <?= !empty($selectedSite['force_https'] ?? false) ? 'selected' : '' ?>>Açık</option>
                        <option value="0" <?= empty($selectedSite['force_https'] ?? false) ? 'selected' : '' ?>>Kapalı</option>
                    </select>
                </div>
                <div class="form-actions"><button type="submit">Ayarı Kaydet</button></div>
            </form>
        </section>
    <?php elseif ($activeModule === 'runtime'): ?>
        <?php $renderGuidePanel('Runtime İşlem Rehberi', 'Runtime değişiklikleri uygulama sürecini ve port/start komutunu etkiler.', [
            'PHP veya start komutunu ayarla.',
            'Start ya da Restart işlemini başlat.',
            'Runtime loglarını kontrol et.',
            'Gerekirse deploy veya proxy ayarına dön.',
        ], [
            'Etkilenen site: ' . (string) ($selectedSite['domain'] ?? '-'),
            'Etkilenen servis: runtime process',
            'Geri alınabilirlik: ayar tekrar kaydedilerek düzeltilebilir',
            'Beklenen süre: kısa iş kuyruğu süresi',
        ], [
            'Deploy çalışırken runtime aksiyonları kilitlenir.',
            'Runtime işi çalışırken yeni start/restart/stop bekler.',
            'Port değişikliği proxy kontrolü gerektirebilir.',
        ], [
            ['href' => '/jobs?q=' . urlencode($selectedSiteId), 'label' => 'İşleri Aç'],
            ['href' => $moduleUrl('deploy', $selectedSiteId), 'label' => 'Deploy Ekranı'],
            ['href' => $moduleUrl('proxy', $selectedSiteId), 'label' => 'Proxy Kontrolü'],
        ]); ?>
        <?php $runtimeSummary = is_array($jobStatusByGroup['runtime'] ?? null) ? $jobStatusByGroup['runtime'] : ['status' => 'Beklenmiyor', 'detail' => '-']; ?>
        <?php
        $runtimeDisableReason = '';
        if (!empty($jobLocks['runtime_locked'])) {
            $runtimeDisableReason = 'Runtime veya deploy işi çalıştığı için bu aksiyon geçici olarak kapalı.';
        } elseif (empty($permissions['runtime_control'])) {
            $runtimeDisableReason = 'Bu işlem için yetkiniz yok.';
        }
        $renderOperationStatusStrip('Runtime İş Durumu', $runtimeSummary, [
            'Start/Restart/Stop işlemleri kuyruk üzerinden çalışır.',
            $runtimeDisableReason !== '' ? $runtimeDisableReason : 'Aksiyonlar kullanılabilir.',
        ], '/jobs?q=' . urlencode($selectedSiteId));
        ?>
        <section class="module-card form-stack-top">
            <h2>Runtime Durumu</h2>
            <table class="compact-table">
                <tbody>
                <tr><th>Durum</th><td><?= $h($runtimeState['status'] ?? 'hazır') ?></td></tr>
                <tr><th>Port</th><td><?= $h($runtimeState['port'] ?? ($deployProfile['port'] ?? '-')) ?></td></tr>
                <tr><th>Start Komutu</th><td><code><?= $h($runtimeState['start_command'] ?? ($deployProfile['start_command'] ?? '-')) ?></code></td></tr>
                <tr><th>Son Deploy</th><td><?= $h($runtimeState['last_deploy_at'] ?? '-') ?></td></tr>
                <tr><th>Son Hata</th><td><?= $h($runtimeState['last_error'] ?? '-') ?></td></tr>
                </tbody>
            </table>
            <div class="inline-actions form-stack-top">
                <form method="post" action="/sites/runtime/control" class="inline-form">
                    <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                    <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                    <input type="hidden" name="action_type" value="start">
                        <button type="submit" <?= (!empty($jobLocks['runtime_locked']) || empty($permissions['runtime_control'])) ? 'disabled' : '' ?>>Start</button>
                </form>
                <form method="post" action="/sites/runtime/control" class="inline-form">
                    <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                    <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                    <input type="hidden" name="action_type" value="restart">
                        <button type="submit" class="button-secondary" <?= (!empty($jobLocks['runtime_locked']) || empty($permissions['runtime_control'])) ? 'disabled' : '' ?>>Restart</button>
                </form>
                <form method="post" action="/sites/runtime/control" class="inline-form">
                    <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                    <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                    <input type="hidden" name="action_type" value="stop">
                        <button type="submit" class="button-secondary" <?= (!empty($jobLocks['runtime_locked']) || empty($permissions['runtime_control'])) ? 'disabled' : '' ?>>Stop</button>
                </form>
            </div>
        </section>
        <section class="metric-grid form-stack-top">
            <div class="module-card"><h3>Durum</h3><p class="metric-value"><?= !empty($wordpress['installed'] ?? false) ? 'Aktif' : 'Hazır' ?></p></div>
            <div class="module-card"><h3>PHP</h3><p class="metric-value"><?= $h($phpProfile['php_version'] ?? '8.3') ?></p></div>
            <div class="module-card"><h3>Veritabanı</h3><p class="metric-value"><?= count($databases) ?></p></div>
            <div class="module-card"><h3>WP Plugin</h3><p class="metric-value"><?= count(is_array($wordpress['plugins'] ?? null) ? $wordpress['plugins'] : []) ?></p></div>
        </section>
        <section class="module-card form-stack-top">
            <h2>WordPress Güvenlik ve Optimizasyon</h2>
            <?php $wpChecklist = is_array($wordpressSecurityReport['checklist'] ?? null) ? $wordpressSecurityReport['checklist'] : []; ?>
            <p class="status-line">Skor: <?= $h((string) ($wordpressSecurityReport['score'] ?? 0)) ?>/100</p>
            <p class="status-line">Kritik Risk: <?= $h((string) ($wordpressSecurityReport['critical_count'] ?? 0)) ?></p>
            <p class="status-line"><?= $h((string) ($wordpressSecurityReport['summary'] ?? '-')) ?></p>
            <p class="status-line">Son Tarama: <?= $h((string) ($wordpressSecurityReport['updated_at'] ?? '-')) ?></p>
            <form method="post" action="/sites/wordpress/security-scan" class="inline-form form-stack-top">
                <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                <button type="submit" class="button-secondary">Güvenlik Taraması Yenile</button>
            </form>
            <?php if ($wpChecklist !== []): ?>
                <table class="compact-table form-stack-top">
                    <thead><tr><th>Kontrol</th><th>Durum</th><th>Seviye</th></tr></thead>
                    <tbody>
                    <?php foreach ($wpChecklist as $item): ?>
                        <tr>
                            <td><?= $h((string) ($item['label'] ?? '')) ?></td>
                            <td><?= !empty($item['ok'] ?? false) ? 'Uygun' : 'Aksiyon Gerekli' ?></td>
                            <td><?= $h((string) ($item['severity'] ?? 'info')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
        <section class="split-grid form-stack-top">
            <div class="module-card">
                <h2>PHP</h2>
                <form method="post" action="/sites/php/save" class="form-block">
                    <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                    <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                    <label>PHP</label>
                    <select name="php_version"><option value="8.1" <?= (($phpProfile['php_version'] ?? '8.3') === '8.1') ? 'selected' : '' ?>>8.1</option><option value="8.2" <?= (($phpProfile['php_version'] ?? '8.3') === '8.2') ? 'selected' : '' ?>>8.2</option><option value="8.3" <?= (($phpProfile['php_version'] ?? '8.3') === '8.3') ? 'selected' : '' ?>>8.3</option></select>
                    <label>Memory</label><input type="text" name="memory_limit" value="<?= $h($phpProfile['memory_limit'] ?? '256M') ?>">
                    <label>Upload</label><input type="text" name="upload_max_filesize" value="<?= $h($phpProfile['upload_max_filesize'] ?? '64M') ?>">
                    <label>Execution</label>
                    <input type="number" min="30" max="600" name="max_execution_time" value="<?= $h($phpProfile['max_execution_time'] ?? 120) ?>" required data-validate data-msg-min="Minimum 30 sn olmalı." data-msg-max="Maksimum 600 sn olmalı." data-msg-required="Execution süresi zorunlu.">
                    <p class="field-hint">Sınır: 30 - 600 sn</p>
                    <div class="form-actions"><button type="submit">Kaydet</button></div>
                </form>
            </div>
            <div class="module-card">
                <h2>WordPress</h2>
                <p class="status-line">Durum: <?= !empty($wordpress['installed'] ?? false) ? 'kurulu' : 'kurulu değil' ?></p>
                <?php $wpPlugins = is_array($wordpress['plugins'] ?? null) ? $wordpress['plugins'] : []; ?>
                <?php $wpThemes = is_array($wordpress['themes'] ?? null) ? $wordpress['themes'] : []; ?>
                <?php $wpOps = is_array($wordpress['last_operations'] ?? null) ? $wordpress['last_operations'] : []; ?>
                <?php $wpStaging = is_array($wordpress['staging'] ?? null) ? $wordpress['staging'] : []; ?>
                <details>
                    <summary>+ WordPress Kur</summary>
                    <form method="post" action="/sites/wordpress/install" class="form-block form-stack-top">
                        <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                        <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                        <label>Admin Kullanıcı</label><input type="text" name="wp_admin_user" required>
                        <label>Admin E-posta</label><input type="email" name="wp_admin_email" required>
                        <label>Admin Şifre</label>
                        <input type="text" name="wp_admin_password" required minlength="10" data-validate data-msg-required="Admin şifresi zorunlu." data-msg-minlength="Admin şifresi en az 10 karakter olmalı.">
                        <p class="field-hint">Minimum: 10 karakter</p>
                        <div class="form-actions"><button type="submit">WordPress Kur</button></div>
                    </form>
                </details>
                <details class="form-stack-top">
                    <summary>+ WordPress İşlemleri</summary>
                    <form method="post" action="/sites/wordpress/plugin" class="form-grid form-stack-top">
                        <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                        <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                        <div><label>Plugin</label><input type="text" name="plugin" placeholder="woocommerce" required></div>
                        <div><label>İşlem</label><select name="action_type"><option value="install">Kur</option><option value="delete">Sil</option></select></div>
                        <div class="form-actions"><button type="submit" class="button-secondary">Uygula</button></div>
                    </form>
                    <form method="post" action="/sites/wordpress/theme" class="form-grid form-stack-top">
                        <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                        <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                        <div><label>Tema</label><input type="text" name="theme" placeholder="astra" required></div>
                        <div class="form-actions"><button type="submit" class="button-secondary">Aktif Et</button></div>
                    </form>
                    <div class="inline-actions form-stack-top">
                        <form method="post" action="/sites/wordpress/core-update" class="inline-form">
                            <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                            <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                            <button type="submit" class="button-secondary">Core Güncelle</button>
                        </form>
                        <form method="post" action="/sites/wordpress/maintenance" class="inline-form">
                            <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                            <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                            <select name="enabled"><option value="1">Maintenance Aç</option><option value="0">Maintenance Kapat</option></select>
                            <button type="submit" class="button-secondary">Kaydet</button>
                        </form>
                    </div>
                </details>
                <details class="form-stack-top">
                    <summary>+ WP-CLI Güvenlik Politikası</summary>
                    <div class="split-grid form-stack-top">
                        <div>
                            <h3>İzinli Komutlar</h3>
                            <ul>
                                <?php foreach ((array) ($wordpressCliPolicy['allowed_commands'] ?? []) as $command): ?>
                                    <li><code><?= $h((string) $command) ?></code></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div>
                            <h3>Engelli Komutlar</h3>
                            <ul>
                                <?php foreach ((array) ($wordpressCliPolicy['blocked_commands'] ?? []) as $command): ?>
                                    <li><code><?= $h((string) $command) ?></code></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </details>
                <details class="form-stack-top">
                    <summary>+ Plugin / Tema Listesi</summary>
                    <div class="split-grid form-stack-top">
                        <div>
                            <h3>Pluginler</h3>
                            <?php if ($wpPlugins === []): ?><p class="empty-state">Kayıtlı plugin yok.</p><?php else: ?>
                                <ul><?php foreach ($wpPlugins as $plugin): ?><li><?= $h((string) $plugin) ?></li><?php endforeach; ?></ul>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h3>Temalar</h3>
                            <?php if ($wpThemes === []): ?><p class="empty-state">Kayıtlı tema yok.</p><?php else: ?>
                                <ul><?php foreach ($wpThemes as $theme): ?><li><?= $h((string) $theme) ?></li><?php endforeach; ?></ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </details>
                <details class="form-stack-top">
                    <summary>+ Son WordPress Operasyonları</summary>
                    <?php if ($wpOps === []): ?>
                        <p class="empty-state">Henüz operasyon kaydı yok.</p>
                    <?php else: ?>
                        <table class="compact-table">
                            <thead><tr><th>Zaman</th><th>Aksiyon</th><th>Hedef</th><th>Durum</th></tr></thead>
                            <tbody>
                            <?php foreach (array_reverse($wpOps) as $op): ?>
                                <tr>
                                    <td><?= $h((string) ($op['time'] ?? '-')) ?></td>
                                    <td><?= $h((string) ($op['action'] ?? '-')) ?></td>
                                    <td><?= $h((string) ($op['target'] ?? '-')) ?></td>
                                    <td><?= !empty($op['ok'] ?? false) ? 'Başarılı' : 'Hata' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </details>
                <details class="form-stack-top">
                    <summary>+ Staging</summary>
                    <p class="status-line">Durum: <?= $h((string) ($wpStaging['status'] ?? 'hazır')) ?></p>
                    <p class="status-line">Staging Domain: <?= $h((string) ($wpStaging['staging_domain'] ?? '-')) ?></p>
                    <p class="status-line">Son Staging Talebi: <?= $h((string) ($wpStaging['last_create_requested_at'] ?? '-')) ?></p>
                    <p class="status-line">Son Live Senkron Talebi: <?= $h((string) ($wpStaging['last_sync_requested_at'] ?? '-')) ?></p>
                    <?php if (((string) ($wpStaging['last_error'] ?? '')) !== ''): ?>
                        <p class="empty-state">Son Hata: <?= $h((string) ($wpStaging['last_error'] ?? '')) ?></p>
                    <?php endif; ?>
                    <div class="inline-actions form-stack-top">
                        <form method="post" action="/sites/wordpress/staging/create" class="inline-form">
                            <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                            <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                            <button type="submit" class="button-secondary">Staging Oluştur</button>
                        </form>
                    </div>
                    <form method="post" action="/sites/wordpress/staging/sync-live" class="form-grid form-stack-top" data-confirm-message="Staging canlı ortama senkronlansın mı?">
                        <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                        <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                        <div><label>Onay için ana domain</label><input type="text" name="confirm_domain" required placeholder="<?= $h((string) ($selectedSite['domain'] ?? '')) ?>"></div>
                        <div class="form-actions"><button type="submit">Staging -> Canlı Senkron</button></div>
                    </form>
                </details>
            </div>
        </section>
        <section class="module-card form-stack-top">
            <h2>Runtime Logları</h2>
            <?php if (empty($runtimeLogs)): ?>
                <p class="empty-state">Henüz runtime log kaydı bulunmuyor.</p>
            <?php else: ?>
                <pre class="log-console"><?php foreach ($runtimeLogs as $line): ?>[<?= $h($line['time'] ?? '') ?>] <?= $h($line['stream'] ?? 'info') ?> - <?= $h($line['message'] ?? '') . "\n" ?><?php endforeach; ?></pre>
            <?php endif; ?>
        </section>
    <?php elseif ($activeModule === 'files'): ?>
        <?php $currentFilePath = (string) ($fileListing['path'] ?? '/'); ?>
        <?php $renderGuidePanel('Dosya İşlem Rehberi', 'Dosya işlemleri seçili sitenin document root alanında çalışır.', [
            'Dizini seç.',
            'Dosya yükle, düzenle veya klasör oluştur.',
            'İzin ve hedef path bilgisini kontrol et.',
            'Riskli değişikliklerden önce backup al.',
        ], [
            'Yükleme hedefi: ' . $currentFilePath,
            'Document root: ' . $documentRoot,
            'Silme işlemi dosya veya klasörü kaldırır',
            'Upload limiti: ' . $fileUploadMaxMb . 'MB',
        ], [
            'Silme/taşıma işlemleri geri alınamaz; backup önerilir.',
            'Aynı path üzerine yükleme mevcut dosyayı etkileyebilir.',
            'Güvenli dosya adı kuralı path traversal girişimlerini engeller.',
        ], [
            ['href' => $moduleUrl('backups', $selectedSiteId), 'label' => 'Önce Backup Al'],
            ['href' => '/jobs?q=' . urlencode($selectedSiteId), 'label' => 'İşleri Aç'],
        ]); ?>
        <section class="module-card form-stack-top">
            <h2>Dosyalar</h2>
            <?php if (($fileListing['ok'] ?? false) !== true): ?>
                <p class="empty-state"><?= $h($fileListing['message'] ?? 'Dosya listesi şu anda okunamıyor.') ?></p>
            <?php else: ?>
                <p>Dizin: <code><?= $h($fileListing['path'] ?? '/') ?></code></p>
                <div class="impact-box form-stack-top">
                    <p><strong>Bu dosya nereye yüklenir?</strong></p>
                    <p>Hedef dizin: <code><?= $h($fileListing['path'] ?? '/') ?></code></p>
                    <p>Limit: <?= $h((string) $fileUploadMaxMb) ?>MB. Güvenli ad kullanın; `../` gibi path dışına çıkma girişimleri engellenir.</p>
                </div>
                <?php if (($fileListing['path'] ?? '') !== ''): ?>
                    <p><a href="/sites/files?site=<?= urlencode($selectedSiteId) ?>&path=<?= urlencode((string) ($fileListing['parent_path'] ?? '')) ?>">Üst dizin</a></p>
                <?php endif; ?>
                <table class="compact-table">
                    <thead><tr><th></th><th>Ad</th><th>Tip</th><th>İzin</th><th>İşlem</th></tr></thead>
                    <tbody>
                    <?php foreach (($fileListing['items'] ?? []) as $item): ?>
                        <tr>
                            <td>
                                <input type="checkbox" form="bulkDeleteForm" name="paths[]" value="<?= $h($item['path'] ?? '') ?>">
                            </td>
                            <td>
                                <?php if (!empty($item['is_dir'] ?? false)): ?>
                                    <a href="/sites/files?site=<?= urlencode($selectedSiteId) ?>&path=<?= urlencode((string) ($item['path'] ?? '')) ?>"><?= $h($item['name'] ?? '') ?>/</a>
                                <?php else: ?>
                                    <a href="/sites/files?site=<?= urlencode($selectedSiteId) ?>&path=<?= urlencode((string) ($fileListing['path'] ?? '')) ?>&file=<?= urlencode((string) ($item['path'] ?? '')) ?>"><?= $h($item['name'] ?? '') ?></a>
                                <?php endif; ?>
                            </td>
                            <td><?= !empty($item['is_dir'] ?? false) ? 'klasör' : 'dosya' ?></td>
                            <td><?= $h($item['permissions'] ?? '') ?></td>
                            <td>
                                <div class="inline-actions">
                                    <?php if (empty($item['is_dir'] ?? false)): ?>
                                        <a href="/sites/file/download?site=<?= urlencode($selectedSiteId) ?>&path=<?= urlencode((string) ($item['path'] ?? '')) ?>" class="status-pill" style="text-decoration:none;">İndir</a>
                                    <?php endif; ?>
                                    <form method="post" action="/sites/file/delete" class="inline-form" data-confirm-message="Öğe silinsin mi?">
                                        <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                                        <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                                        <input type="hidden" name="path" value="<?= $h($item['path'] ?? '') ?>">
                                        <button type="submit" class="button-secondary">Sil</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <form method="post" action="/sites/file/bulk-delete" class="inline-form form-stack-top" id="bulkDeleteForm" data-confirm-message="Seçili öğeler silinsin mi?">
                    <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                    <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                    <button type="submit" class="button-secondary">Seçili Öğeleri Sil</button>
                </form>
                <details class="form-stack-top">
                    <summary>+ Dosya Yükle</summary>
                    <form method="post" action="/sites/file/upload" class="form-grid form-stack-top" enctype="multipart/form-data">
                        <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                        <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                        <input type="hidden" name="current_path" value="<?= $h($fileListing['path'] ?? '') ?>">
                        <div class="form-field-full">
                            <label>Dosya</label>
                            <input type="file" name="upload_file" required>
                            <p class="field-hint">Maksimum dosya boyutu: <?= $h((string) $fileUploadMaxMb) ?>MB. Hedef: <?= $h($fileListing['path'] ?? '/') ?>. Dosya adında path dışına çıkma karakterleri kullanmayın.</p>
                        </div>
                        <div class="form-actions form-field-full"><button type="submit">Yükle</button></div>
                    </form>
                </details>
                <details class="form-stack-top">
                    <summary>+ Yeni Klasör</summary>
                    <form method="post" action="/sites/file/create-directory" class="form-grid form-stack-top">
                        <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                        <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                        <input type="hidden" name="current_path" value="<?= $h($fileListing['path'] ?? '') ?>">
                        <div><label>Klasör adı</label><input type="text" name="directory_name" required></div>
                        <div class="form-actions"><button type="submit">Oluştur</button></div>
                    </form>
                </details>
                <details class="form-stack-top">
                    <summary>+ Taşı / Kopyala</summary>
                    <div class="split-grid form-stack-top">
                        <form method="post" action="/sites/file/move" class="form-grid">
                            <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                            <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                            <div class="form-field-full"><label>Kaynak Yol</label><input type="text" name="source_path" required placeholder="klasor/dosya.txt"></div>
                            <div class="form-field-full"><label>Hedef Yol</label><input type="text" name="destination_path" required placeholder="hedef/dosya.txt"></div>
                            <div class="form-actions form-field-full"><button type="submit">Taşı</button></div>
                        </form>
                        <form method="post" action="/sites/file/copy" class="form-grid">
                            <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                            <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                            <div class="form-field-full"><label>Kaynak Yol</label><input type="text" name="source_path" required placeholder="klasor/dosya.txt"></div>
                            <div class="form-field-full"><label>Hedef Yol</label><input type="text" name="destination_path" required placeholder="hedef/dosya-kopya.txt"></div>
                            <div class="form-actions form-field-full"><button type="submit" class="button-secondary">Kopyala</button></div>
                        </form>
                    </div>
                </details>
                <?php $selectedFile = (string) ($selectedFile ?? ''); ?>
                <?php if ($selectedFile !== '' && ($fileContent['ok'] ?? false) === true): ?>
                    <details class="form-stack-top" open>
                        <summary>Dosya Düzenle: <?= $h($selectedFile) ?></summary>
                        <form method="post" action="/sites/file/save" class="form-block form-stack-top">
                            <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                            <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                            <input type="hidden" name="path" value="<?= $h($selectedFile) ?>">
                            <textarea name="content" rows="16" class="code-editor"><?= $h($fileContent['content'] ?? '') ?></textarea>
                            <div class="form-actions"><button type="submit">Kaydet</button></div>
                        </form>
                    </details>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php elseif ($activeModule === 'proxy'): ?>
        <section class="module-card form-stack-top">
            <h2>Route / Proxy Yönetimi</h2>
            <?php if (empty($proxyRoutes)): ?>
                <p class="empty-state">Henüz proxy route kaydı bulunmuyor. İlk route kuralını ekleyin.</p>
            <?php else: ?>
                <table class="compact-table">
                    <thead><tr><th>Prefix</th><th>Hedef Port</th><th>Açıklama</th><th>Durum</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($proxyRoutes as $route): ?>
                        <tr>
                            <td><?= $h($route['prefix'] ?? '') ?></td>
                            <td><?= $h($route['target_port'] ?? '') ?></td>
                            <td><?= $h($route['description'] ?? '') ?></td>
                            <td><?= $h($route['status'] ?? 'active') ?></td>
                            <td>
                                <form method="post" action="/sites/proxy/delete-route" class="inline-form" data-confirm-message="Proxy route silinsin mi?">
                                    <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                                    <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                                    <input type="hidden" name="route_id" value="<?= $h($route['id'] ?? '') ?>">
                                    <button type="submit" class="button-secondary">Sil</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <div class="form-actions"><button type="button" data-open-drawer="drawerProxyAdd">Route Ekle</button></div>
        </section>
    <?php elseif ($activeModule === 'deploy'): ?>
        <?php $renderGuidePanel('Deploy İşlem Rehberi', 'Deploy işlemi dosyaları, runtime durumunu ve yayın akışını etkileyebilir.', [
            'Deploy profilini kaydet.',
            'Deploy işlemini başlat.',
            'Runtime durumunu kontrol et.',
            'Proxy ve SSL durumunu kontrol et.',
        ], [
            'Etkilenen site: ' . (string) ($selectedSite['domain'] ?? '-'),
            'Etkilenen alan: dosyalar ve runtime state',
            'Geri alınabilirlik: önceki backup veya yeni deploy ile',
            'Beklenen süre: build ve kaynak tipine bağlı',
        ], [
            'Backup/restore/deploy aktifken yeni deploy kapalıdır.',
            'Deploy hatasında job log bağlantısı kontrol edilir.',
            'Port/start değişikliği runtime kontrolü gerektirir.',
        ], [
            ['href' => '/jobs?q=' . urlencode($selectedSiteId), 'label' => 'Job Logları'],
            ['href' => $moduleUrl('runtime', $selectedSiteId), 'label' => 'Runtime Kontrolü'],
            ['href' => $moduleUrl('backups', $selectedSiteId), 'label' => 'Backup Ekranı'],
        ]); ?>
        <?php $deploySummary = is_array($jobStatusByGroup['deploy'] ?? null) ? $jobStatusByGroup['deploy'] : ['status' => 'Beklenmiyor', 'detail' => '-']; ?>
        <?php
        $deployDisableReason = '';
        if (!empty($jobLocks['deploy_locked'])) {
            $deployDisableReason = 'Backup/restore/deploy işi aktifken yeni deploy başlatılamaz.';
        } elseif (empty($permissions['deploy_run'])) {
            $deployDisableReason = 'Deploy başlatmak için yetkiniz yok.';
        }
        $renderOperationStatusStrip('Deploy İş Durumu', $deploySummary, [
            'Profil kaydı ve deploy çalıştırma ayrı adımlardır.',
            $deployDisableReason !== '' ? $deployDisableReason : 'Deploy aksiyonu kullanılabilir.',
        ], '/jobs?q=' . urlencode($selectedSiteId));
        ?>
        <section class="split-grid form-stack-top">
            <div class="module-card">
                <h2>Deploy Profili</h2>
                <form method="post" action="/sites/deploy/save" class="form-block">
                    <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                    <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                    <label>Kaynak</label>
                    <select name="source_type">
                        <?php $sourceType = (string) ($deployProfile['source_type'] ?? 'manual'); ?>
                        <option value="manual" <?= $sourceType === 'manual' ? 'selected' : '' ?>>Manuel Upload</option>
                        <option value="git" <?= $sourceType === 'git' ? 'selected' : '' ?>>Git Repository</option>
                        <option value="zip" <?= $sourceType === 'zip' ? 'selected' : '' ?>>ZIP Yükle</option>
                        <option value="directory" <?= $sourceType === 'directory' ? 'selected' : '' ?>>Var Olan Dizin</option>
                    </select>
                    <label>Kaynak Değeri</label>
                    <input type="text" name="source_value" placeholder="https://repo.git veya /var/www/app" value="<?= $h($deployProfile['source_value'] ?? '') ?>">
                    <label>Build Komutu</label>
                    <input type="text" name="build_command" placeholder="npm run build" value="<?= $h($deployProfile['build_command'] ?? '') ?>">
                    <label>Start Komutu</label>
                    <input type="text" name="start_command" placeholder="npm start" value="<?= $h($deployProfile['start_command'] ?? '') ?>">
                    <label>Port</label>
                    <input type="number" name="port" min="1" max="65535" value="<?= $h($deployProfile['port'] ?? 3000) ?>" required data-validate data-msg-min="Port en az 1 olmalı." data-msg-max="Port en fazla 65535 olabilir." data-msg-required="Port zorunlu.">
                    <p class="field-hint">Sınır: 1 - 65535</p>
                    <label>Webhook Branch</label>
                    <input type="text" name="webhook_branch" placeholder="main" value="<?= $h($deployProfile['webhook_branch'] ?? 'main') ?>">
                    <label>Webhook Secret</label>
                    <input type="password" name="webhook_secret" placeholder="degistirmek icin yeni secret girin" value="" minlength="12" data-validate data-msg-minlength="Webhook secret en az 12 karakter olmalı.">
                    <p class="field-hint">Boş bırakırsanız mevcut secret korunur. Yeni değer için minimum 12 karakter.</p>
                    <div class="form-actions"><button type="submit">Deploy Profilini Kaydet</button></div>
                </form>
            </div>
            <div class="module-card">
                <h2>Yayınlama</h2>
                <?php if ($deployProfile === []): ?>
                    <p class="empty-state">Henüz deploy profili bulunmuyor. Önce profil kaydedin.</p>
                <?php else: ?>
                    <p class="status-line">Kaynak: <?= $h($deployProfile['source_type'] ?? '-') ?></p>
                    <p class="status-line">Port: <?= $h($deployProfile['port'] ?? '-') ?></p>
                    <p class="status-line">Webhook Branch: <?= $h($deployProfile['webhook_branch'] ?? 'main') ?></p>
                    <p class="status-line">Webhook Secret: <?= ((bool) ($deployProfile['webhook_secret_configured'] ?? false)) ? 'tanımlı' : 'tanımlı değil' ?></p>
                    <p class="status-line">Webhook URL: <code><?= $h('/webhooks/deploy?site=' . $selectedSiteId) ?></code></p>
                    <p class="status-line">Son güncelleme: <?= $h($deployProfile['updated_at'] ?? '-') ?></p>
                    <div class="impact-box form-stack-top">
                        <p><strong>Deploy Etkisi</strong></p>
                        <p>Dosyalar ve runtime durumu değişebilir. Hata halinde iş logunu açın.</p>
                    </div>
                    <form method="post" action="/sites/deploy/run" class="form-stack-top">
                        <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                        <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                        <button type="submit" <?= (!empty($jobLocks['deploy_locked']) || empty($permissions['deploy_run'])) ? 'disabled' : '' ?>>Deploy Et</button>
                    </form>
                    <?php if ($deployDisableReason !== ''): ?>
                        <p class="field-hint form-stack-top"><?= $h($deployDisableReason) ?></p>
                    <?php endif; ?>
                <?php endif; ?>
                <p class="form-stack-top status-line">Deploy işlemi kuyruğa alınır. Durumu İşler ekranından takip edebilirsiniz.</p>
            </div>
        </section>
    <?php elseif ($activeModule === 'mail'): ?>
        <section class="split-grid form-stack-top">
            <div class="module-card">
                <h2>Webmail</h2>
                <?php $webmailInstalled = ((bool) ($webmailSettings['installed'] ?? false)) === true; ?>
                <?php $webmailUrl = (string) ($webmailSettings['base_url'] ?? ''); ?>
                <p>Sağlayıcı: <?= $h((string) ($webmailSettings['provider'] ?? 'roundcube')) ?></p>
                <p>Durum: <?= $h($webmailInstalled ? 'Kurulu' : 'Kurulu değil') ?></p>
                <form method="post" action="/sites/mail/webmail/save" class="form-grid form-stack-top">
                    <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                    <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                    <div>
                        <label>Sağlayıcı</label>
                        <select name="provider">
                            <option value="roundcube" selected>Roundcube</option>
                        </select>
                    </div>
                    <div>
                        <label>Webmail URL</label>
                        <input type="url" name="base_url" value="<?= $h($webmailUrl) ?>" required>
                    </div>
                    <div>
                        <label><input type="checkbox" name="installed" value="1" <?= $webmailInstalled ? 'checked' : '' ?>> Kurulum tamamlandı</label>
                    </div>
                    <div class="form-actions">
                        <button type="submit">Webmail Ayarını Kaydet</button>
                        <?php if ($webmailInstalled && $webmailUrl !== ''): ?>
                            <a href="<?= $h($webmailUrl) ?>" target="_blank" rel="noopener noreferrer" class="button-secondary">Webmail Aç</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <div class="module-card">
                <h2>Mail Domain Kayıtları</h2>
                <?php $recommendedRecords = is_array($mailDomainRecords['records'] ?? null) ? $mailDomainRecords['records'] : []; ?>
                <?php if ($recommendedRecords === []): ?>
                    <p class="empty-state">Önerilen kayıtlar yüklenemedi.</p>
                <?php else: ?>
                    <table class="compact-table">
                        <thead><tr><th>Tür</th><th>Ad</th><th>Değer</th></tr></thead>
                        <tbody>
                        <?php foreach ($recommendedRecords as $record): ?>
                            <tr>
                                <td><?= $h((string) ($record['label'] ?? $record['type'] ?? '')) ?></td>
                                <td><?= $h((string) ($record['name'] ?? '')) ?></td>
                                <td><code><?= $h((string) ($record['value'] ?? '')) ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                <form method="post" action="/sites/mail/domain-records/apply" class="form-grid form-stack-top">
                    <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                    <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                    <div>
                        <label>DMARC Politikası</label>
                        <select name="dmarc_policy">
                            <option value="none">none</option>
                            <option value="quarantine" selected>quarantine</option>
                            <option value="reject">reject</option>
                        </select>
                    </div>
                    <div class="form-actions"><button type="submit">Kayıtları DNS'e Uygula</button></div>
                </form>
            </div>
            <div class="module-card">
            <h2>Mail</h2>
            <p>Postfix: <?= $h($mailHealth['postfix'] ?? 'unknown') ?> | Dovecot: <?= $h($mailHealth['dovecot'] ?? 'unknown') ?> | Kuyruk: <?= $h($mailHealth['queue_size'] ?? '0') ?></p>
            <?php if (empty($mailboxes)): ?>
                <p class="empty-state">Henüz mailbox kaydı bulunmuyor. İlk mailbox hesabını ekleyin.</p>
            <?php else: ?>
                <table class="compact-table">
                    <thead><tr><th>E-posta</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($mailboxes as $mailbox): ?>
                        <tr>
                            <td><?= $h($mailbox['email'] ?? '') ?></td>
                            <td>
                                <form method="post" action="/sites/mailbox/delete" class="inline-form" data-confirm-message="Mailbox silinsin mi?">
                                    <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                                    <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                                    <input type="hidden" name="mailbox_id" value="<?= $h($mailbox['id'] ?? '') ?>">
                                    <button type="submit" class="button-secondary">Sil</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <div class="form-actions"><button type="button" data-open-drawer="drawerMailboxAdd">Mailbox Oluştur</button></div>
            </div>
        </section>
        <section class="split-grid form-stack-top">
            <div class="module-card">
                <h2>Mail Kuyruğu</h2>
                <?php if ($mailQueue === []): ?>
                    <p class="empty-state">Kuyrukta bekleyen veya hatalı mail yok.</p>
                <?php else: ?>
                    <table class="compact-table">
                        <thead><tr><th>Alıcı</th><th>Konu</th><th>Durum</th><th>Retry</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($mailQueue as $item): ?>
                            <tr>
                                <td><?= $h((string) ($item['recipient'] ?? '-')) ?></td>
                                <td><?= $h((string) ($item['subject'] ?? '-')) ?></td>
                                <td><?= $h((string) ($item['status'] ?? 'pending')) ?></td>
                                <td><?= $h((string) ($item['retry_count'] ?? 0)) ?></td>
                                <td>
                                    <div class="inline-actions">
                                        <form method="post" action="/sites/mail/queue/retry" class="inline-form">
                                            <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                                            <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                                            <input type="hidden" name="queue_id" value="<?= $h((string) ($item['id'] ?? '')) ?>">
                                            <button type="submit" class="button-secondary">Retry</button>
                                        </form>
                                        <form method="post" action="/sites/mail/queue/remove" class="inline-form" data-confirm-message="Kuyruk öğesi kaldırılsın mı?">
                                            <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                                            <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                                            <input type="hidden" name="queue_id" value="<?= $h((string) ($item['id'] ?? '')) ?>">
                                            <button type="submit" class="button-secondary">Kaldır</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php if (((string) ($item['last_error'] ?? '')) !== ''): ?>
                                <tr><td colspan="5"><small>Son hata: <?= $h((string) ($item['last_error'] ?? '')) ?></small></td></tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            <div class="module-card">
                <h2>Teslimat Logları</h2>
                <?php if ($mailDeliveryLogs === []): ?>
                    <p class="empty-state">Henüz teslimat log kaydı yok.</p>
                <?php else: ?>
                    <table class="compact-table">
                        <thead><tr><th>Zaman</th><th>Aksiyon</th><th>Alıcı</th><th>Mesaj</th></tr></thead>
                        <tbody>
                        <?php foreach ($mailDeliveryLogs as $log): ?>
                            <tr>
                                <td><?= $h((string) ($log['created_at'] ?? '-')) ?></td>
                                <td><?= $h((string) ($log['action'] ?? '-')) ?></td>
                                <td><?= $h((string) ($log['recipient'] ?? '-')) ?></td>
                                <td><?= $h((string) ($log['message'] ?? '-')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </section>
    <?php elseif ($activeModule === 'dns'): ?>
        <?php $renderGuidePanel('DNS İşlem Rehberi', 'DNS kayıtları domain çözümlemesini, SSL doğrulamasını ve mail teslimatını etkiler.', [
            'Kayıt ekle veya mevcut kaydı düzenle.',
            'Kayıtları DNS\'e uygula.',
            'Domain çözümlemesini doğrula.',
            'Mail veya SSL etkisini kontrol et.',
        ], [
            'A kaydı örneği: @ -> sunucu IP',
            'MX kaydı örneği: @ -> mail.example.com',
            'TXT kaydı örneği: SPF veya domain doğrulama',
            'Beklenen süre: TTL ve DNS yayılımına bağlı',
        ], [
            'Yanlış A kaydı site erişimini kesebilir.',
            'Yanlış MX/TXT mail teslimatını etkileyebilir.',
            'DNS apply işi çalışırken sonucu İşler ekranından izleyin.',
        ], [
            ['href' => '/jobs?q=' . urlencode($selectedSiteId), 'label' => 'DNS İşlerini Aç'],
            ['href' => $moduleUrl('mail', $selectedSiteId), 'label' => 'Mail Kontrolü'],
            ['href' => $moduleUrl('ssl', $selectedSiteId), 'label' => 'SSL Kontrolü'],
        ]); ?>
        <?php $dnsSummary = is_array($jobStatusByGroup['dns_proxy'] ?? null) ? $jobStatusByGroup['dns_proxy'] : ['status' => 'Beklenmiyor', 'detail' => '-']; ?>
        <?php
        $dnsBusy = in_array((string) ($dnsSummary['status'] ?? ''), ['Bekliyor', 'Çalışıyor'], true);
        $renderOperationStatusStrip('DNS İş Durumu', $dnsSummary, [
            'DNS apply sonucu yayılım süresine bağlıdır.',
            $dnsBusy ? 'DNS/Proxy işi aktif: yeni kayıt değişikliklerinde çakışma riski olabilir.' : 'DNS işlemleri beklemede değil.',
        ], '/jobs?q=' . urlencode($selectedSiteId));
        ?>
        <section class="module-card form-stack-top">
            <h2>DNS</h2>
            <?php if (empty($dnsRecords)): ?>
                <p class="empty-state">Henüz DNS kaydı bulunmuyor. İlk kaydı ekleyin.</p>
            <?php else: ?>
                <table class="compact-table">
                    <thead><tr><th>Tip</th><th>Ad</th><th>Değer</th><th>TTL</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($dnsRecords as $record): ?>
                        <tr>
                            <td><?= $h($record['type'] ?? '') ?></td>
                            <td><?= $h($record['name'] ?? '') ?></td>
                            <td><?= $h($record['value'] ?? '') ?></td>
                            <td><?= $h($record['ttl'] ?? '') ?></td>
                            <td>
                                <form method="post" action="/sites/dns/delete-record" class="inline-form" data-confirm-message="DNS kaydı silinsin mi?">
                                    <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                                    <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                                    <input type="hidden" name="record_id" value="<?= $h($record['id'] ?? '') ?>">
                                    <button type="submit" class="button-secondary" <?= empty($permissions['dns_delete']) ? 'disabled' : '' ?>>Sil</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <div class="form-actions"><button type="button" data-open-drawer="drawerDnsAdd">DNS Kaydı Ekle</button></div>
            <?php if (empty($permissions['dns_delete'])): ?>
                <p class="field-hint form-stack-top"><?= $h((string) ($permissionHints['dns_delete'] ?? '')) ?></p>
            <?php endif; ?>
            <?php if ($dnsBusy): ?>
                <p class="field-hint form-stack-top">Aktif DNS/Proxy işi tamamlanana kadar apply sonucu beklenmelidir.</p>
            <?php endif; ?>
        </section>
    <?php elseif ($activeModule === 'backups'): ?>
        <?php $renderGuidePanel('Backup ve Restore İşlem Rehberi', 'Restore işlemi mevcut dosya veya veritabanı durumunu değiştirebilir.', [
            'Backup al.',
            'Bütünlük bilgisini kontrol et.',
            'Restore öncesi dry-run çalıştır.',
            'Dry-run başarılıysa restore et.',
        ], [
            'Etkilenen site: ' . (string) ($selectedSite['domain'] ?? '-'),
            'Restore mevcut durumu değiştirebilir',
            'Geri alınabilirlik: restore öncesi safety backup ile',
            'Beklenen süre: backup boyutuna bağlı',
        ], [
            'Deploy/backup/restore aktifken restore kapalıdır.',
            'Dry-run başarılı değilse restore başlatılmamalıdır.',
            'Restore sonrası runtime/proxy kontrolü gerekebilir.',
        ], [
            ['href' => '/jobs?q=' . urlencode($selectedSiteId), 'label' => 'İşleri Aç'],
            ['href' => $moduleUrl('runtime', $selectedSiteId), 'label' => 'Runtime Kontrolü'],
        ]); ?>
        <?php $backupSummary = is_array($jobStatusByGroup['backup_restore'] ?? null) ? $jobStatusByGroup['backup_restore'] : ['status' => 'Beklenmiyor', 'detail' => '-']; ?>
        <?php
        $backupCreateReason = '';
        if (!empty($jobLocks['backup_locked'])) {
            $backupCreateReason = 'Restore veya deploy işi aktifken yeni backup başlatılamaz.';
        }
        $backupRestoreReason = '';
        if (!empty($jobLocks['restore_locked'])) {
            $backupRestoreReason = 'Backup/restore/deploy işi aktifken restore işlemleri bekletilir.';
        } elseif (empty($permissions['backup_restore'])) {
            $backupRestoreReason = 'Restore işlemi için yetkiniz yok.';
        }
        $renderOperationStatusStrip('Backup / Restore İş Durumu', $backupSummary, [
            'Restore öncesi dry-run zorunludur.',
            $backupCreateReason !== '' ? $backupCreateReason : 'Yeni backup aksiyonu kullanılabilir.',
            $backupRestoreReason !== '' ? $backupRestoreReason : 'Restore aksiyonları kurala uygunsa kullanılabilir.',
        ], '/jobs?q=' . urlencode($selectedSiteId));
        ?>
        <section class="module-card form-stack-top">
            <h2>Backup</h2>
            <div class="impact-box">
                <p><strong>Restore Riski</strong></p>
                <p>Restore mevcut dosya/veritabanı durumunu değiştirebilir. Önce dry-run çalıştırın ve mümkünse yeni backup alın.</p>
            </div>
            <div class="form-actions"><button type="button" data-open-drawer="drawerBackupCreate" <?= !empty($jobLocks['backup_locked']) ? 'disabled' : '' ?>>Yeni Backup Al</button></div>
            <?php if ($backupCreateReason !== ''): ?>
                <p class="field-hint"><?= $h($backupCreateReason) ?></p>
            <?php endif; ?>
            <table class="compact-table">
                <thead><tr><th>ID</th><th>Tip</th><th>Bütünlük</th><th>Dry-Run</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($backups as $backup): ?>
                    <?php $dryRunOk = !empty($backup['dry_run_ok'] ?? false); ?>
                    <tr>
                        <td><?= $h($backup['id'] ?? '') ?></td>
                        <td><?= $h($backup['type'] ?? '') ?></td>
                        <td>
                            <?php if (!empty($backup['checksum_sha256'] ?? '')): ?>
                                <?= !empty($backup['integrity_ok'] ?? false) ? 'doğrulandı' : 'hatalı' ?>
                            <?php else: ?>
                                doğrulama yok
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($backup['dry_run_at'] ?? '')): ?>
                                <?= !empty($backup['dry_run_ok'] ?? false) ? 'ok' : 'hata' ?><br>
                                <small><?= $h($backup['dry_run_at'] ?? '') ?></small>
                            <?php else: ?>
                                yok
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="inline-actions">
                                <form method="post" action="/sites/backup/dry-run" class="inline-form">
                                    <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                                    <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                                    <input type="hidden" name="backup_id" value="<?= $h($backup['id'] ?? '') ?>">
                                    <button type="submit" class="button-secondary" <?= (!empty($jobLocks['restore_locked']) || empty($permissions['backup_restore'])) ? 'disabled' : '' ?>>Dry-Run</button>
                                </form>
                                <form method="post" action="/sites/backup/restore" class="inline-form" data-confirm-message="Backup geri yüklensin mi? Bu işlem mevcut veriyi değiştirebilir.">
                                    <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                                    <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                                    <input type="hidden" name="backup_id" value="<?= $h($backup['id'] ?? '') ?>">
                                    <button type="submit" class="button-secondary" <?= (!empty($jobLocks['restore_locked']) || empty($permissions['backup_restore']) || !$dryRunOk) ? 'disabled' : '' ?>>Restore</button>
                                </form>
                            </div>
                            <?php if (!$dryRunOk): ?>
                                <p class="field-hint">Restore için önce başarılı dry-run gerekir.</p>
                            <?php endif; ?>
                            <?php if ($backupRestoreReason !== ''): ?>
                                <p class="field-hint"><?= $h($backupRestoreReason) ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php elseif ($activeModule === 'security'): ?>
        <section class="split-grid form-stack-top">
            <div class="module-card">
                <h2>Güvenlik Durumu</h2>
                <p>Firewall: <?= $h($securityStatus['firewall']['status'] ?? 'unknown') ?></p>
                <p>Fail2ban: <?= $h($securityStatus['fail2ban']['status'] ?? 'unknown') ?></p>
            </div>
            <div class="module-card">
                <h2>FTP</h2>
                <table class="compact-table">
                    <thead>
                    <tr>
                        <th>Kullanıcı</th>
                        <th>Kök Dizin</th>
                        <th>Durum</th>
                        <th>İşlem</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($ftpAccounts === []): ?>
                        <tr><td colspan="4">Henüz FTP hesabı yok.</td></tr>
                    <?php else: ?>
                        <?php foreach ($ftpAccounts as $ftp): ?>
                            <?php $ftpId = (string) ($ftp['id'] ?? ''); ?>
                            <?php $isDisabled = (bool) ($ftp['is_disabled'] ?? false); ?>
                            <tr>
                                <td><?= $h($ftp['username'] ?? '') ?></td>
                                <td><code><?= $h((string) ($ftp['home_path'] ?? '-')) ?></code></td>
                                <td><?= $h($isDisabled ? 'Pasif' : 'Aktif') ?></td>
                                <td>
                                    <div class="inline-actions">
                                        <form method="post" action="/sites/ftp/set-status" class="inline-form">
                                            <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                                            <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                                            <input type="hidden" name="ftp_account_id" value="<?= $h($ftpId) ?>">
                                            <input type="hidden" name="disabled" value="<?= $isDisabled ? '0' : '1' ?>">
                                            <button type="submit" class="button-secondary"><?= $isDisabled ? 'Aktifleştir' : 'Pasifleştir' ?></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="4">
                                    <form method="post" action="/sites/ftp/reset-password" class="form-grid">
                                        <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                                        <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                                        <input type="hidden" name="ftp_account_id" value="<?= $h($ftpId) ?>">
                                        <div><label>Yeni Şifre (<?= $h((string) ($ftp['username'] ?? '')) ?>)</label><input type="text" name="ftp_password" minlength="10" required></div>
                                        <div class="form-actions"><button type="submit">Şifre Yenile</button></div>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
                <form method="post" action="/sites/ftp/create" class="form-grid form-stack-top">
                    <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                    <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                    <div><label>Kullanıcı</label><input type="text" name="ftp_user" required></div>
                    <div><label>Şifre</label><input type="text" name="ftp_password" required></div>
                    <div class="form-actions"><button type="submit">FTP Hesabı Oluştur</button></div>
                </form>
            </div>
        </section>
        <section class="module-card form-stack-top">
            <h2>FTP İşlem Geçmişi</h2>
            <table class="compact-table">
                <thead><tr><th>Zaman</th><th>İşlem</th><th>Sonuç</th></tr></thead>
                <tbody>
                <?php
                $ftpActivityRows = array_values(array_filter($siteActivities, static function (array $row): bool {
                    return str_starts_with((string) ($row['action'] ?? ''), 'ftp.');
                }));
                ?>
                <?php if ($ftpActivityRows === []): ?>
                    <tr><td colspan="3">Henüz FTP işlemi yok.</td></tr>
                <?php else: ?>
                    <?php foreach (array_slice($ftpActivityRows, 0, 10) as $activity): ?>
                        <?php $actionName = (string) ($activity['action'] ?? ''); ?>
                        <tr>
                            <td><?= $h((string) ($activity['time'] ?? '-')) ?></td>
                            <td><?= $h($actionName) ?></td>
                            <td><?= $h(str_ends_with($actionName, '_failed') ? 'Hata' : 'Başarılı') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </section>
        <?php $renderGuidePanel('Tehlikeli İşlem Rehberi', 'Bu alandaki işlemler site erişimini veya kalıcı veriyi etkileyebilir.', [
            'Önce backup al.',
            'Etkilenen siteyi ve path bilgisini kontrol et.',
            'Onay metnini doğru gir.',
            'İş sonucunu loglardan takip et.',
        ], [
            'Site kaydı ve ilişkili kaynaklar etkilenir',
            'Geri alınabilirlik: sadece geçerli backup varsa',
            'Önerilen ön işlem: backup al',
            'Beklenen süre: silinecek kaynağa bağlı',
        ], [
            'Silme işlemi geri alınamaz.',
            'Aktif deploy/backup işi varken tehlikeli işlem yapılmamalıdır.',
            'Yetki yoksa işlem kapalı kalır.',
        ], [
            ['href' => $moduleUrl('backups', $selectedSiteId), 'label' => 'Önce Backup Al'],
            ['href' => '/logs?source=activity&site=' . urlencode($selectedSiteId), 'label' => 'Logları Aç'],
        ]); ?>
        <section class="module-card form-stack-top danger-zone">
            <h2>Tehlikeli İşlemler</h2>
            <p>Bu alandaki işlemler geri alınamaz veya servis kesintisine neden olabilir.</p>
            <div class="impact-box form-stack-top">
                <p><strong>Site Silme Etkisi</strong></p>
                <p>Site kaydı, domain ilişkileri ve siteye bağlı operasyon state'i etkilenir. Geri dönüş için önce backup alın.</p>
            </div>
            <form method="post" action="/sites/delete" class="form-grid" data-confirm-message="Site tamamen silinsin mi? Bu işlem geri alınamaz.">
                <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
                <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
                <div><label>Onay için ana domaini yazın</label><input type="text" name="confirm_domain" required placeholder="<?= $h($selectedSite['domain'] ?? '') ?>"></div>
                <div class="form-actions"><button type="submit" class="button-secondary" <?= empty($permissions['site_delete']) ? 'disabled' : '' ?>>Siteyi Sil</button></div>
            </form>
            <?php if (empty($permissions['site_delete'])): ?>
                <p class="field-hint form-stack-top"><?= $h((string) ($permissionHints['site_delete'] ?? '')) ?></p>
            <?php endif; ?>
        </section>
    <?php endif; ?>
<?php endif; ?>

<?php if ($selectedSiteId !== ''): ?>
    <aside id="drawerDomainAdd" class="drawer" hidden>
        <div class="drawer-header"><h3 class="drawer-title">Domain Ekle</h3><button type="button" class="button-secondary" data-close-drawer>Kapat</button></div>
        <form method="post" action="/sites/domain/add" class="form-grid form-stack-top">
            <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
            <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
            <div class="form-field-full">
                <label>Yeni domain</label>
                <input type="text" name="domain" placeholder="blog.example.com" required pattern="^(?=.{1,253}$)(?!-)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$" data-validate data-msg-required="Domain zorunlu." data-msg-pattern="Geçerli bir domain girin.">
                <p class="field-hint">Örnek: blog.example.com</p>
            </div>
            <div class="form-actions form-field-full"><button type="submit">Domain Ekle</button></div>
        </form>
    </aside>
    <aside id="drawerProxyAdd" class="drawer" hidden>
        <div class="drawer-header"><h3 class="drawer-title">Proxy Route Ekle</h3><button type="button" class="button-secondary" data-close-drawer>Kapat</button></div>
        <form method="post" action="/sites/proxy/add-route" class="form-grid form-stack-top">
            <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
            <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
            <div>
                <label>Prefix</label>
                <input type="text" name="prefix" placeholder="/api" required pattern="^/[a-zA-Z0-9/_-]{0,120}$" data-validate data-msg-required="Prefix zorunlu." data-msg-pattern="Prefix / ile başlamalı ve geçerli karakterler içermeli.">
                <p class="field-hint">Örnek: /api veya /v1/auth</p>
            </div>
            <div>
                <label>Hedef Port</label>
                <input type="number" min="1" max="65535" name="target_port" placeholder="4000" required data-validate data-msg-min="Hedef port en az 1 olmalı." data-msg-max="Hedef port en fazla 65535 olabilir." data-msg-required="Hedef port zorunlu.">
                <p class="field-hint">Sınır: 1 - 65535</p>
            </div>
            <div class="form-field-full"><label>Açıklama</label><input type="text" name="description" placeholder="Backend API"></div>
            <div class="form-actions form-field-full"><button type="submit">Kaydet</button></div>
        </form>
    </aside>
    <aside id="drawerMailboxAdd" class="drawer" hidden>
        <div class="drawer-header"><h3 class="drawer-title">Mailbox Oluştur</h3><button type="button" class="button-secondary" data-close-drawer>Kapat</button></div>
        <form method="post" action="/sites/mailbox/create" class="form-grid form-stack-top">
            <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
            <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
            <div>
                <label>Mailbox</label>
                <input type="text" name="local_part" required pattern="^[a-z0-9._-]{2,64}$" data-validate data-msg-required="Mailbox adı zorunlu." data-msg-pattern="Mailbox adı 2-64 karakter ve küçük harf/rakam içermeli.">
                <p class="field-hint">Format: 2-64 karakter (a-z, 0-9, . _ -)</p>
            </div>
            <div>
                <label>Şifre</label>
                <input type="text" name="mail_password" required minlength="10" data-validate data-msg-required="Mailbox şifresi zorunlu." data-msg-minlength="Mailbox şifresi en az 10 karakter olmalı.">
                <p class="field-hint">Minimum: 10 karakter</p>
            </div>
            <div class="form-actions form-field-full"><button type="submit">Mailbox Oluştur</button></div>
        </form>
    </aside>
    <aside id="drawerDnsAdd" class="drawer" hidden>
        <div class="drawer-header"><h3 class="drawer-title">DNS Kaydı Ekle</h3><button type="button" class="button-secondary" data-close-drawer>Kapat</button></div>
        <form method="post" action="/sites/dns/add-record" class="form-grid form-stack-top">
            <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
            <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
            <div><label>Tip</label><select name="record_type"><option value="A">A</option><option value="AAAA">AAAA</option><option value="CNAME">CNAME</option><option value="MX">MX</option><option value="TXT">TXT</option><option value="NS">NS</option><option value="SOA">SOA</option></select></div>
            <div>
                <label>Ad</label>
                <input type="text" name="record_name" required data-validate data-msg-required="Kayıt adı zorunlu.">
                <p class="field-hint">Örnek: @, www, mail, _dmarc</p>
            </div>
            <div class="form-field-full">
                <label>Değer</label>
                <input type="text" name="record_value" required data-validate data-msg-required="Kayıt değeri zorunlu.">
                <p class="field-hint">Kayıt tipine uygun değer girin (IP, domain veya TXT metni).</p>
            </div>
            <div>
                <label>TTL</label>
                <input type="number" name="record_ttl" value="3600" min="60" max="86400" required data-validate data-msg-min="TTL en az 60 sn olmalı." data-msg-max="TTL en fazla 86400 sn olabilir." data-msg-required="TTL zorunlu.">
                <p class="field-hint">Sınır: 60 - 86400 sn</p>
            </div>
            <div class="form-actions form-field-full"><button type="submit">DNS Kaydı Ekle</button></div>
        </form>
    </aside>
    <aside id="drawerBackupCreate" class="drawer" hidden>
        <div class="drawer-header"><h3 class="drawer-title">Yeni Backup</h3><button type="button" class="button-secondary" data-close-drawer>Kapat</button></div>
        <form method="post" action="/sites/backup/create" class="form-grid form-stack-top">
            <input type="hidden" name="_csrf" value="<?= $h($csrfToken ?? '') ?>">
            <input type="hidden" name="site_id" value="<?= $h($selectedSiteId) ?>">
            <div class="form-field-full"><label>Backup Tipi</label><select name="backup_type"><option value="full">Full</option><option value="files">Dosyalar</option><option value="database">Veritabanı</option></select></div>
            <div class="form-actions form-field-full"><button type="submit">Backup Al</button></div>
        </form>
    </aside>
<?php endif; ?>

<?php
$content = (string) ob_get_clean();
$layoutMode = 'app';
$navActive = 'sites';
$navModule = $activeModule;
$activeSiteId = $selectedSiteId;
require AILHOST_ROOT . '/resources/views/layout.php';
