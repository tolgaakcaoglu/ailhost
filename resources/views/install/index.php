<?php
declare(strict_types=1);

$layoutMode = 'install';
$state = is_array($state ?? null) ? $state : [];
$settingsData = is_array($settingsData ?? null) ? $settingsData : [];
$adminData = is_array($adminData ?? null) ? $adminData : [];
$requirements = is_array($requirements ?? null) ? $requirements : [];
$requestedStep = (string) ($preferredStep ?? '');
$allowedSteps = ['welcome', 'requirements', 'settings', 'admin'];
$activeStep = in_array($requestedStep, $allowedSteps, true) ? $requestedStep : 'welcome';

$savedServerName = (string) ($settingsData['server_name'] ?? $settingsData['hostname'] ?? 'ail-server-01');
$savedTimezone = (string) ($settingsData['timezone'] ?? 'UTC');
$savedLogRetention = (string) ($settingsData['log_retention_days'] ?? '30');
$savedWebRoot = (string) ($settingsData['default_web_root'] ?? '/var/www/html');
$savedBackupPath = (string) ($settingsData['backup_path'] ?? '/mnt/backups/ailhost');
$savedName = (string) ($adminData['name'] ?? '');
$savedEmail = (string) ($adminData['email'] ?? '');

$requirementByLabel = [];
foreach ($requirements as $item) {
    $name = (string) ($item['name'] ?? '');
    $label = '';
    if (str_contains($name, 'İşletim sistemi')) {
        $label = 'İşletim Sistemi';
    } elseif (str_contains($name, 'Disk')) {
        $label = 'Disk Alanı';
    } elseif (str_contains($name, 'RAM')) {
        $label = 'RAM';
    } elseif (str_contains($name, 'CPU')) {
        $label = 'CPU';
    } elseif (str_contains($name, 'nginx')) {
        $label = 'Nginx';
    } elseif ($name === 'Docker') {
        $label = 'Docker';
    } elseif (str_contains($name, 'PHP')) {
        $label = 'PHP';
    } elseif ($name === 'E-posta Servisi') {
        $label = 'E-posta Servisi';
    }

    if ($label !== '' && !isset($requirementByLabel[$label])) {
        $requirementByLabel[$label] = $item;
    }
}

$requirementRows = [];
foreach (['İşletim Sistemi', 'Disk Alanı', 'RAM', 'CPU', 'Nginx', 'Docker', 'PHP', 'E-posta Servisi'] as $label) {
    $item = $requirementByLabel[$label] ?? [];
    $ok = (bool) ($item['ok'] ?? false);
    $critical = (bool) ($item['critical'] ?? false);
    $requirementRows[] = [
        'label' => $label,
        'current' => (!$ok && !$critical) ? 'Otomatik olarak yüklenecek.' : (string) ($item['current'] ?? 'tespit edilemedi'),
        'ok' => $ok,
        'critical' => $critical,
    ];
}
$hasBlockingRequirement = false;
foreach ($requirementRows as $row) {
    if (($row['critical'] ?? false) === true && ($row['ok'] ?? false) !== true) {
        $hasBlockingRequirement = true;
        break;
    }
}

$icon = static function (string $name, string $class = ''): string {
    $classAttr = $class !== '' ? ' class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"' : '';
    $paths = [
        'world' => '<circle cx="12" cy="12" r="9"/><path d="M3.6 9h16.8M3.6 15h16.8M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
        'github' => '<path d="M9 19c-4.5 1.5-4.5-2.5-6-3m12 5v-3.9c0-1 .1-1.4-.5-2 2.8-.3 5.5-1.4 5.5-6a4.6 4.6 0 0 0-1.3-3.2 4.2 4.2 0 0 0-.1-3.2s-1.1-.3-3.5 1.3a12.3 12.3 0 0 0-6.2 0C6.5 1.4 5.4 1.7 5.4 1.7a4.2 4.2 0 0 0-.1 3.2A4.6 4.6 0 0 0 4 8.1c0 4.6 2.7 5.7 5.5 6-.6.6-.6 1.2-.5 2V21"/>',
        'phone' => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A15 15 0 0 1 3 6a2 2 0 0 1 2-2"/>',
        'sliders' => '<path d="M4 8h4m4 0h8M4 16h10m4 0h2"/><circle cx="10" cy="8" r="2"/><circle cx="16" cy="16" r="2"/>',
        'shield' => '<path d="M12 3l7 3v5c0 5-3 8.5-7 10-4-1.5-7-5-7-10V6l7-3z"/><path d="M9 12l2 2 4-4"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'grid' => '<path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/>',
        'server' => '<path d="M5 6h14v5H5zM5 13h14v5H5z"/><path d="M8 8h.01M8 15h.01"/>',
        'network' => '<circle cx="6" cy="6" r="2"/><circle cx="18" cy="6" r="2"/><circle cx="12" cy="18" r="2"/><path d="M8 7l3 8M16 7l-3 8"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'arrow-right' => '<path d="M5 12h14"/><path d="M13 6l6 6-6 6"/>',
        'arrow-left' => '<path d="M19 12H5"/><path d="M11 6l-6 6 6 6"/>',
        'check' => '<path d="M5 12l4 4L19 6"/>',
        'alert' => '<path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 4.3 2.7 18a2 2 0 0 0 1.7 3h15.2a2 2 0 0 0 1.7-3L13.7 4.3a2 2 0 0 0-3.4 0z"/>',
        'gear' => '<path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8z"/><path d="M4 12h2m12 0h2M12 4v2m0 12v2M6.5 6.5l1.4 1.4m8.2 8.2 1.4 1.4M17.5 6.5l-1.4 1.4m-8.2 8.2-1.4 1.4"/>',
        'folder' => '<path d="M4 6h6l2 2h8v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/>',
        'archive' => '<path d="M4 7h16M6 7v12h12V7M8 4h8l2 3H6z"/><path d="M10 11h4"/>',
        'eye' => '<path d="M2 12s4-6 10-6 10 6 10 6-4 6-10 6S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'chevron-down' => '<path d="M6 9l6 6 6-6"/>',
    ];

    return '<svg' . $classAttr . ' width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
};

$stepIndex = ['welcome' => 1, 'requirements' => 2, 'settings' => 3, 'admin' => 4][$activeStep] ?? 1;
$renderStepper = static function (int $stepIndex) use ($icon, $state): void {
    $steps = [
        ['label' => 'Hoşgeldin', 'icon' => 'grid', 'step' => 'welcome', 'done' => true],
        ['label' => 'Sistem Gereksinimleri', 'icon' => 'server', 'step' => 'requirements', 'done' => true],
        ['label' => 'Ağ Ayarları', 'icon' => 'network', 'step' => 'settings', 'done' => (bool) ($state['settings_saved'] ?? false)],
        ['label' => 'Yönetici', 'icon' => 'user', 'step' => 'admin', 'done' => (bool) ($state['admin_created'] ?? false)],
    ];
    ?>
    <nav class="setup-stepper" aria-label="Kurulum adımları">
        <?php foreach ($steps as $i => $step): ?>
            <?php $number = $i + 1; ?>
            <?php $canOpen = $number <= $stepIndex || (bool) ($step['done'] ?? false); ?>
            <?php if ($canOpen): ?>
                <a href="/install?step=<?= htmlspecialchars((string) $step['step'], ENT_QUOTES, 'UTF-8') ?>" class="setup-step <?= $number <= $stepIndex ? 'is-active' : '' ?> is-clickable">
                    <span class="setup-step-circle"><?= $icon($step['icon']) ?></span>
                    <span><?= htmlspecialchars($step['label'], ENT_QUOTES, 'UTF-8') ?></span>
                </a>
            <?php else: ?>
                <div class="setup-step <?= $number <= $stepIndex ? 'is-active' : '' ?> is-locked">
                    <span class="setup-step-circle"><?= $icon($step['icon']) ?></span>
                    <span><?= htmlspecialchars($step['label'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
        <div class="setup-step-track" aria-hidden="true">
            <span class="setup-step-track-fill" style="width: <?= (int) max(0, min(100, (($stepIndex - 1) / 3) * 100)) ?>%"></span>
            <?php for ($i = 1; $i <= 4; $i++): ?>
                <span class="setup-track-dot <?= $i < $stepIndex ? 'is-filled' : ($i === $stepIndex ? 'is-current' : '') ?>"></span>
            <?php endfor; ?>
        </div>
    </nav>
    <?php
};

ob_start();
?>
<main class="setup-shell">
    <header class="setup-topbar">
        <a class="setup-brand" href="/install?step=welcome" aria-label="ailpanel">
            <img src="/assets/icon.png" alt="">
            <span>ailpanel</span>
        </a>
        <nav class="setup-links" aria-label="Kurulum bağlantıları">
            <a href="#"><?= $icon('world') ?><span>Website</span></a>
            <a href="#"><?= $icon('github') ?><span>Github</span></a>
            <a href="#"><?= $icon('phone') ?><span>İletişim</span></a>
            <a href="#"><span>v1.0.0-alpha</span></a>
        </nav>
    </header>

    <section class="setup-content">
    <?php if ($activeStep === 'welcome'): ?>
        <div class="setup-hero setup-hero-welcome">
            <h1>Hoşgeldiniz</h1>
            <p class="setup-copy">Sunucunuzu profesyonel standartlarda yönetmeniz için tasarlandı. Basit, hızlı ve tam kontrollü bir kurulum süreci sizi bekliyor.</p>
            <div class="setup-feature-grid">
                <article class="setup-feature-card">
                    <?= $icon('sliders', 'setup-feature-icon') ?>
                    <h2>Endüstriyel Kontrol</h2>
                    <p>Mikro seviyede kaynak ve servis yönetimi.</p>
                </article>
                <article class="setup-feature-card">
                    <?= $icon('shield', 'setup-feature-icon') ?>
                    <h2>Endüstriyel Kontrol</h2>
                    <p>Mikro seviyede kaynak ve servis yönetimi.</p>
                </article>
                <article class="setup-feature-card">
                    <?= $icon('clock', 'setup-feature-icon') ?>
                    <h2>Endüstriyel Kontrol</h2>
                    <p>Mikro seviyede kaynak ve servis yönetimi.</p>
                </article>
            </div>
            <a class="setup-pill setup-pill-light" href="/install?step=requirements">
                <span>Kuruluma Başla</span>
                <span class="setup-pill-icon"><?= $icon('arrow-right') ?></span>
            </a>
        </div>
    <?php elseif ($activeStep === 'requirements'): ?>
        <div class="setup-hero">
            <h1>Sistem Gereksinimleri</h1>
            <p class="setup-copy">Sunucunuzun kurulum için gerekli tüm ön koşulları karşıladığını doğruluyoruz.</p>
            <?php if ($hasBlockingRequirement): ?>
                <button class="setup-pill setup-pill-danger" type="button" disabled>Desteklenmiyor</button>
            <?php else: ?>
                <a class="setup-pill setup-pill-green" href="/install?step=settings">
                    <span>Sonraki Adıma Geç</span>
                    <span class="setup-pill-icon"><?= $icon('arrow-right') ?></span>
                </a>
            <?php endif; ?>

            <div class="setup-card setup-requirements-card is-animated">
                <div class="setup-table-head">
                    <span>GEREKSİNİM</span>
                    <span>DURUM</span>
                </div>
                <?php foreach ($requirementRows as $rowIndex => $row): ?>
                    <?php
                    $ok = (bool) ($row['ok'] ?? false);
                    $critical = (bool) ($row['critical'] ?? false);
                    $statusClass = $ok ? 'setup-status setup-status-ok' : ($critical ? 'setup-status setup-status-error' : 'setup-status setup-status-warn');
                    ?>
                    <div class="setup-requirement-row" data-requirement-row style="animation-delay: <?= (int) ($rowIndex * 300) ?>ms">
                        <div>
                            <strong><?= htmlspecialchars((string) $row['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <span class="<?= (!$ok && !$critical) ? 'is-installable' : '' ?>"><?= htmlspecialchars((string) $row['current'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="<?= $statusClass ?>" title="<?= $ok ? 'Uygun' : ($critical ? 'Eksik' : 'Kurulumda tamamlanabilir') ?>">
                            <span class="setup-status-icon is-loading"><?= $icon($ok ? 'check' : ($critical ? 'alert' : 'gear')) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php elseif ($activeStep === 'settings'): ?>
        <form method="post" action="/install/settings" class="setup-hero setup-form" data-install-settings>
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <h1>Ağ Ayarları</h1>
            <p class="setup-copy">Ağ tercihlerinizi, depolama yollarınızı ve günlük kaydı davranışınızı yapılandırın.</p>
            <button class="setup-pill setup-pill-gray" type="submit" data-settings-submit>
                <span class="setup-pill-icon"><?= $icon('arrow-right') ?></span>
                <span>Sonraki Adıma Geç</span>
            </button>

            <div class="setup-card setup-settings-card">
                <div class="setup-settings-top">
                    <label class="setup-field">
                        <span>SERVER ADI</span>
                        <input type="text" name="server_name" value="<?= htmlspecialchars($savedServerName, ENT_QUOTES, 'UTF-8') ?>" placeholder="ail-server-01" required>
                        <small>Kümeniz içindeki bu örneğe ait benzersiz bir tanımlayıcı.</small>
                    </label>
                    <label class="setup-field setup-field-icon">
                        <span>SİSTEM SAATİ</span>
                        <select name="timezone">
                            <option value="UTC" <?= $savedTimezone === 'UTC' ? 'selected' : '' ?>>UTC (Eşgüdümlü Evrensel Zaman)</option>
                            <option value="Europe/Istanbul" <?= $savedTimezone === 'Europe/Istanbul' ? 'selected' : '' ?>>Europe/Istanbul</option>
                        </select>
                        <?= $icon('chevron-down') ?>
                        <small>Cron görevlerini ve günlük zaman damgalarını etkiler.</small>
                    </label>
                    <label class="setup-field">
                        <span>KAYIT SAKLAMA SÜRESİ</span>
                        <span class="setup-input-with-unit">
                            <input type="number" name="log_retention_days" min="1" max="365" value="<?= htmlspecialchars($savedLogRetention, ENT_QUOTES, 'UTF-8') ?>" required>
                            <b>GÜN</b>
                        </span>
                        <small>Sistem ve erişim kayıtlarının rotasyondan önce saklanması gereken gün sayısı.</small>
                    </label>
                </div>
                <label class="setup-field setup-field-full setup-field-icon">
                    <span>VARSAYILAN WEB DİZİNİ</span>
                    <input type="text" name="default_web_root" value="<?= htmlspecialchars($savedWebRoot, ENT_QUOTES, 'UTF-8') ?>" required autocomplete="off" spellcheck="false" data-dir-input="web_root">
                    <input type="hidden" name="create_default_web_root" value="0" data-dir-create="web_root">
                    <?= $icon('folder') ?>
                    <div class="setup-dir-suggestions" data-dir-suggestions="web_root" hidden></div>
                    <small>Herkese açık web içeriklerinin sunulduğu mutlak yol.</small>
                </label>
                <label class="setup-field setup-field-full setup-field-icon">
                    <span>YEDEKLEME DİZİNİ</span>
                    <input type="text" name="backup_path" value="<?= htmlspecialchars($savedBackupPath, ENT_QUOTES, 'UTF-8') ?>" required autocomplete="off" spellcheck="false" data-dir-input="backup_path">
                    <input type="hidden" name="create_backup_path" value="0" data-dir-create="backup_path">
                    <?= $icon('archive') ?>
                    <div class="setup-dir-suggestions" data-dir-suggestions="backup_path" hidden></div>
                    <small>Otomatik sistem durumu ve veritabanı dökümlerinin alınacağı konum.</small>
                </label>
                <label class="setup-field setup-field-full" data-sudo-field hidden>
                    <span>SUDO ŞİFRESİ (GEREKİRSE)</span>
                    <input type="password" name="sudo_password" placeholder="Dizin oluşturma için sudo şifresi" autocomplete="current-password">
                    <button class="setup-eye-toggle" type="button" data-settings-password-toggle="sudo_password" aria-label="Şifreyi göster"><?= $icon('eye') ?></button>
                    <small>Sadece izin gerektiren yeni dizinler oluşturulurken kullanılır, kaydedilmez.</small>
                </label>
            </div>
        </form>
    <?php elseif ($activeStep === 'admin'): ?>
        <form method="post" action="/install/admin" class="setup-hero setup-form" data-install-admin>
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="finish_install" value="1">
            <h1>Yönetici Oluştur</h1>
            <p class="setup-copy">Bu kullanıcı, Ailpanelin ilk yöneticisi olacak.</p>
            <button class="setup-pill setup-pill-gray" type="submit" data-admin-submit>
                <span>Kurulumu Bitir</span>
                <span class="setup-pill-icon"><?= $icon('arrow-right') ?></span>
            </button>

            <div class="setup-card setup-admin-card">
                <label class="setup-field">
                    <span>İSİM</span>
                    <input type="text" name="name" placeholder="Adınızı girin" value="<?= htmlspecialchars($savedName, ENT_QUOTES, 'UTF-8') ?>" required>
                </label>
                <label class="setup-field">
                    <span>E-POSTA</span>
                    <input type="email" name="email" placeholder="admin@ailpanel.com" value="<?= htmlspecialchars($savedEmail, ENT_QUOTES, 'UTF-8') ?>" required>
                </label>
                <label class="setup-field setup-field-icon">
                    <span>ŞİFRE</span>
                    <input type="password" name="password" minlength="8" required autocomplete="new-password">
                    <button class="setup-eye-toggle" type="button" data-password-toggle="password" aria-label="Şifreyi göster"><?= $icon('eye') ?></button>
                    <small>En az 8 karakter, güçlü bir şifre belirle.</small>
                </label>
                <label class="setup-field setup-field-icon" data-confirm-field>
                    <span>ŞİFREYİ ONAYLA</span>
                    <input type="password" name="password_confirm" minlength="8" required autocomplete="new-password">
                    <button class="setup-eye-toggle" type="button" data-password-toggle="password_confirm" aria-label="Şifre tekrarını göster"><?= $icon('eye') ?></button>
                    <small data-confirm-error hidden>Şifreler uyuşmuyor.</small>
                </label>
            </div>
        </form>
    <?php endif; ?>
    </section>

    <footer class="setup-bottom">
        <?php if ($activeStep !== 'welcome'): ?>
            <?php $renderStepper($stepIndex); ?>
        <?php endif; ?>
        <div class="setup-footer">AILDEV SOFTWARE</div>
    </footer>
</main>

<script>
(() => {
    const settingsForm = document.querySelector('[data-install-settings]');
    if (settingsForm) {
        const submit = settingsForm.querySelector('[data-settings-submit]');
        const fields = Array.from(settingsForm.querySelectorAll('input[required], select[required]'));
        const dirFields = Array.from(settingsForm.querySelectorAll('[data-dir-input]'));
        const dirState = new Map();
        const dirCreateState = new Map();
        const dirCache = new Map();
        const sudoField = settingsForm.querySelector('[data-sudo-field]');
        const sudoInput = settingsForm.querySelector('input[name="sudo_password"]');
        const portal = document.createElement('div');
        portal.className = 'setup-dir-portal';
        document.body.appendChild(portal);
        const openMenus = [];
        let activeDirInput = null;

        const shorten = (path) => {
            if (path === '/') return '/';
            const normalized = String(path || '').replace(/\/+$/, '');
            const name = normalized.split('/').pop() || normalized;
            return name.length > 11 ? `${name.slice(0, 11)}....` : name;
        };

        const clearMenusFrom = (level = 0) => {
            while (openMenus.length > level) {
                const menu = openMenus.pop();
                menu?.remove();
            }
        };

        const closeAllMenus = () => {
            clearMenusFrom(0);
            activeDirInput = null;
        };

        const placeMenu = (menu, preferredX, preferredY) => {
            menu.style.left = `${preferredX}px`;
            menu.style.top = `${preferredY}px`;
            requestAnimationFrame(() => {
                const rect = menu.getBoundingClientRect();
                let x = preferredX;
                let y = preferredY;
                if (rect.right > window.innerWidth - 8) {
                    x = Math.max(8, window.innerWidth - rect.width - 8);
                }
                if (rect.bottom > window.innerHeight - 8) {
                    y = Math.max(8, window.innerHeight - rect.height - 8);
                }
                menu.style.left = `${x}px`;
                menu.style.top = `${y}px`;
            });
        };

        const fetchDirPayload = async (path) => {
            const key = String(path || '');
            if (dirCache.has(key)) {
                return dirCache.get(key);
            }
            const res = await fetch(`/api/install/dir-suggestions?path=${encodeURIComponent(key)}`, { headers: { Accept: 'application/json' } });
            const payload = await res.json();
            dirCache.set(key, payload);
            return payload;
        };

        const applyPathSelection = (input, path, canCreate) => {
            const type = input.getAttribute('data-dir-input');
            if (!type) return;
            const createInput = settingsForm.querySelector(`[data-dir-create="${type}"]`);
            input.value = path;
            if (createInput) {
                createInput.value = canCreate ? '1' : '0';
                createInput.dataset.createdPath = canCreate ? path : '';
            }
            input.setCustomValidity('');
            input.classList.remove('is-invalid');
            dirState.set(input.name, true);
            sync();
            closeAllMenus();
        };

        const buildMenu = (input, payload, level, anchorRect, parentWidth = 0) => {
            clearMenusFrom(level);
            const suggestions = Array.isArray(payload?.suggestions) ? payload.suggestions : [];
            const childrenMap = payload && typeof payload === 'object' ? (payload.children_map || {}) : {};
            const canCreate = payload?.can_create === true;
            const createCandidate = String(payload?.create_candidate || '');
            if (suggestions.length === 0 && !canCreate) {
                return;
            }

            const menu = document.createElement('div');
            menu.className = 'setup-dir-menu';
            menu.dataset.level = String(level);
            const refWidth = Math.min(280, Math.max(188, ...suggestions.map((item) => Math.min(280, 78 + String(item).length * 6))));
            menu.style.width = `${refWidth}px`;

            suggestions.forEach((path) => {
                const hasChildren = childrenMap[path] === true;
                const row = document.createElement('div');
                row.className = `setup-dir-row ${hasChildren ? 'has-children' : 'no-children'}`;
                row.title = path;
                row.innerHTML = `<span class="setup-dir-row-label">${shorten(path)}</span><span class="setup-dir-row-caret">›</span>`;

                row.addEventListener('click', (event) => {
                    event.stopPropagation();
                    applyPathSelection(input, path, false);
                });

                row.addEventListener('mouseenter', async () => {
                    clearMenusFrom(level + 1);
                    if (!hasChildren) return;
                    const childPayload = await fetchDirPayload(path.endsWith('/') ? path : `${path}/`);
                    const rowRect = row.getBoundingClientRect();
                    const nextAnchor = { left: rowRect.right, top: rowRect.top };
                    buildMenu(input, childPayload, level + 1, nextAnchor, rowRect.width);
                });

                menu.appendChild(row);
            });

            if (canCreate && createCandidate !== '') {
                const createRow = document.createElement('div');
                createRow.className = 'setup-dir-row setup-dir-create-action no-children';
                createRow.title = createCandidate;
                createRow.innerHTML = `<span class="setup-dir-row-label">Oluştur: ${shorten(createCandidate)}</span><span class="setup-dir-row-caret">+</span>`;
                createRow.addEventListener('click', (event) => {
                    event.stopPropagation();
                    applyPathSelection(input, createCandidate, true);
                });
                menu.appendChild(createRow);
            }

            if (suggestions.length === 0 && !canCreate) {
                const state = document.createElement('p');
                state.className = 'setup-dir-menu-state';
                state.textContent = 'Alt klasör yok';
                menu.appendChild(state);
            }

            portal.appendChild(menu);
            openMenus.push(menu);

            if (level === 0) {
                placeMenu(menu, anchorRect.left, anchorRect.bottom + 4);
            } else {
                let x = anchorRect.left + 6;
                let y = anchorRect.top;
                const rect = menu.getBoundingClientRect();
                if (x + rect.width > window.innerWidth - 8) {
                    x = Math.max(8, anchorRect.left - parentWidth - rect.width - 6);
                }
                placeMenu(menu, x, y);
            }
        };

        const sync = () => {
            const valid = fields.every((field) => field.checkValidity()) && dirFields.every((field) => dirState.get(field.name) === true);
            submit?.classList.toggle('setup-pill-green', valid);
            submit?.classList.toggle('setup-pill-gray', !valid);
        };

        const syncSudoVisibility = () => {
            if (!sudoField) return;
            const visible = dirFields.some((field) => dirCreateState.get(field.name) === true);
            sudoField.hidden = !visible;
            if (!visible && sudoInput) {
                sudoInput.value = '';
            }
        };
        fields.forEach((field) => {
            field.addEventListener('input', sync);
            field.addEventListener('change', sync);
        });

        const checkDirectory = async (input) => {
            const type = input.getAttribute('data-dir-input');
            if (!type) return;
            const createInput = settingsForm.querySelector(`[data-dir-create="${type}"]`);
            const value = input.value.trim();
            const createdPath = createInput?.dataset.createdPath || '';
            const createSelected = createInput?.value === '1' && createdPath === value;
            if (value === '' || !value.startsWith('/')) {
                if (createInput) {
                    createInput.value = '0';
                    createInput.dataset.createdPath = '';
                }
                input.setCustomValidity('Mutlak dizin yolu gerekli.');
                input.classList.add('is-invalid');
                dirState.set(input.name, false);
                sync();
                return;
            }

            if (createSelected) {
                input.setCustomValidity('');
                input.classList.remove('is-invalid');
                dirState.set(input.name, true);
                dirCreateState.set(input.name, true);
                syncSudoVisibility();
                sync();
                return;
            }

            try {
                const payload = await fetchDirPayload(value);
                const suggestions = Array.isArray(payload.suggestions) ? payload.suggestions : [];
                const matchesExact = suggestions.some((item) => item === value);
                if (matchesExact) {
                    if (createInput) {
                        createInput.value = '0';
                        createInput.dataset.createdPath = '';
                    }
                    input.setCustomValidity('');
                    input.classList.remove('is-invalid');
                    dirState.set(input.name, true);
                    dirCreateState.set(input.name, false);
                } else if (payload.can_create === true) {
                    input.setCustomValidity('Dizin yok. Öneriden "Otomatik oluştur" seçin.');
                    input.classList.add('is-invalid');
                    dirState.set(input.name, false);
                    dirCreateState.set(input.name, true);
                } else {
                    input.setCustomValidity('Dizin bulunamadı. Önerilerden birini seçin.');
                    input.classList.add('is-invalid');
                    dirState.set(input.name, false);
                    dirCreateState.set(input.name, false);
                }
            } catch (_e) {
                input.setCustomValidity('Dizin kontrolü yapılamadı.');
                input.classList.add('is-invalid');
                dirState.set(input.name, false);
                dirCreateState.set(input.name, false);
            }
            syncSudoVisibility();
            sync();
        };

            dirFields.forEach((input) => {
            dirState.set(input.name, false);
            dirCreateState.set(input.name, false);
            let timer = 0;
            input.addEventListener('input', () => {
                const type = input.getAttribute('data-dir-input');
                if (type) {
                    const createInput = settingsForm.querySelector(`[data-dir-create="${type}"]`);
                    if (createInput && createInput.dataset.createdPath && createInput.dataset.createdPath !== input.value.trim()) {
                        createInput.value = '0';
                        createInput.dataset.createdPath = '';
                    }
                }
                window.clearTimeout(timer);
                timer = window.setTimeout(async () => {
                    await checkDirectory(input);
                    if (activeDirInput !== input) {
                        activeDirInput = input;
                    }
                    const value = input.value.trim();
                    const payload = await fetchDirPayload(value === '' ? '/' : value);
                    const rect = input.getBoundingClientRect();
                    buildMenu(input, payload, 0, rect);
                }, 220);
            });
            input.addEventListener('focus', async () => {
                activeDirInput = input;
                const value = input.value.trim();
                const payload = await fetchDirPayload(value === '' ? '/' : value);
                const rect = input.getBoundingClientRect();
                buildMenu(input, payload, 0, rect);
            });
            input.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeAllMenus();
                }
            });
            input.addEventListener('blur', () => checkDirectory(input));
            checkDirectory(input);
        });
        document.addEventListener('click', (event) => {
            if (!(event.target instanceof Element)) return;
            if (event.target.closest('.setup-dir-menu') || event.target.closest('[data-dir-input]')) return;
            closeAllMenus();
        });
        window.addEventListener('resize', closeAllMenus);
        settingsForm.addEventListener('submit', closeAllMenus);
        settingsForm.querySelectorAll('[data-settings-password-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                const target = settingsForm.querySelector(`input[name="${button.getAttribute('data-settings-password-toggle')}"]`);
                if (!target) return;
                const nextType = target.getAttribute('type') === 'password' ? 'text' : 'password';
                target.setAttribute('type', nextType);
                button.setAttribute('aria-label', nextType === 'password' ? 'Şifreyi göster' : 'Şifreyi gizle');
            });
        });
        syncSudoVisibility();
        sync();
    }

    document.querySelectorAll('[data-requirement-row]').forEach((row, index) => {
        const statusIcon = row.querySelector('.setup-status-icon');
        window.setTimeout(() => {
            statusIcon?.classList.remove('is-loading');
        }, (index * 300) + 300);
    });

    const form = document.querySelector('[data-install-admin]');
    if (!form) return;
    const password = form.querySelector('input[name="password"]');
    const confirm = form.querySelector('input[name="password_confirm"]');
    const field = form.querySelector('[data-confirm-field]');
    const error = form.querySelector('[data-confirm-error]');
    const submit = form.querySelector('[data-admin-submit]');
    const requiredFields = Array.from(form.querySelectorAll('input[required]'));
    if (!password || !confirm || !field || !error) return;

    const sync = () => {
        const mismatch = confirm.value.length > 0 && password.value !== confirm.value;
        field.classList.toggle('is-invalid', mismatch);
        error.hidden = !mismatch;
        confirm.setCustomValidity(mismatch ? 'Şifreler uyuşmuyor.' : '');
        const valid = requiredFields.every((input) => input.checkValidity()) && !mismatch;
        submit?.classList.toggle('setup-pill-green', valid);
        submit?.classList.toggle('setup-pill-gray', !valid);
    };

    requiredFields.forEach((input) => input.addEventListener('input', sync));
    form.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const target = form.querySelector(`input[name="${button.getAttribute('data-password-toggle')}"]`);
            if (!target) return;
            const nextType = target.getAttribute('type') === 'password' ? 'text' : 'password';
            target.setAttribute('type', nextType);
            button.setAttribute('aria-label', nextType === 'password' ? 'Şifreyi göster' : 'Şifreyi gizle');
        });
    });
    sync();
})();
</script>
<?php
$content = (string) ob_get_clean();
require AILHOST_ROOT . '/resources/views/layout.php';
