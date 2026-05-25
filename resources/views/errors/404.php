<?php
declare(strict_types=1);

ob_start();
?>
<h1>404</h1>
<p>Sayfa bulunamadı: <code><?= htmlspecialchars($path ?? '', ENT_QUOTES, 'UTF-8') ?></code></p>
<p>Mevcut sayfalar: <code>/</code>, <code>/install</code>, <code>/dashboard</code></p>
<?php
$content = (string) ob_get_clean();
require AILHOST_ROOT . '/resources/views/layout.php';
