<?php

declare(strict_types=1);

namespace Ailhost\Services;

final class FileManagerService
{
    private const DEFAULT_MAX_UPLOAD_MB = 20;

    public function __construct(
        private readonly SiteService $siteService,
        private readonly array $paths
    )
    {
    }

    public function list(string $siteId, string $relativePath = ''): array
    {
        [$basePath, $targetPath, $relativePath] = $this->resolvePath($siteId, $relativePath);
        if ($basePath === null || $targetPath === null) {
            return ['ok' => false, 'message' => 'Website bulunamadı veya dizin geçersiz.'];
        }
        if (!is_dir($targetPath)) {
            return ['ok' => false, 'message' => 'Dizin bulunamadı.'];
        }

        $items = [];
        $entries = scandir($targetPath);
        if ($entries === false) {
            return ['ok' => false, 'message' => 'Dizin okunamadı.'];
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $targetPath . DIRECTORY_SEPARATOR . $entry;
            $isDir = is_dir($full);
            $items[] = [
                'name' => $entry,
                'path' => ltrim($relativePath . '/' . $entry, '/'),
                'is_dir' => $isDir,
                'size' => $isDir ? null : filesize($full),
                'permissions' => $this->permissions($full),
                'updated_at' => date(DATE_ATOM, (int) filemtime($full)),
            ];
        }

        usort($items, static function (array $a, array $b): int {
            if ((bool) $a['is_dir'] !== (bool) $b['is_dir']) {
                return ((bool) $a['is_dir']) ? -1 : 1;
            }
            return strcmp((string) $a['name'], (string) $b['name']);
        });

        return [
            'ok' => true,
            'base_path' => $basePath,
            'path' => $relativePath,
            'items' => $items,
            'parent_path' => $this->parentPath($relativePath),
        ];
    }

    public function saveFile(string $siteId, string $relativePath, string $content): array
    {
        [, $targetPath, ] = $this->resolvePath($siteId, $relativePath);
        if ($targetPath === null) {
            return ['ok' => false, 'message' => 'Dosya yolu geçersiz.'];
        }
        $dir = dirname($targetPath);
        if (!is_dir($dir) && !mkdir($concurrentDirectory = $dir, 0755, true) && !is_dir($concurrentDirectory)) {
            return ['ok' => false, 'message' => 'Dizin oluşturulamadı.'];
        }
        if (file_put_contents($targetPath, $content) === false) {
            return ['ok' => false, 'message' => 'Dosya kaydedilemedi.'];
        }
        return ['ok' => true, 'message' => 'Dosya kaydedildi.'];
    }

    public function deletePath(string $siteId, string $relativePath): array
    {
        [, $targetPath, ] = $this->resolvePath($siteId, $relativePath);
        if ($targetPath === null || !file_exists($targetPath)) {
            return ['ok' => false, 'message' => 'Silinecek öğe bulunamadı.'];
        }
        if (is_dir($targetPath)) {
            $entries = scandir($targetPath);
            if ($entries === false || count(array_diff($entries, ['.', '..'])) > 0) {
                return ['ok' => false, 'message' => 'Sadece boş klasör silinebilir.'];
            }
            if (!rmdir($targetPath)) {
                return ['ok' => false, 'message' => 'Klasör silinemedi.'];
            }
            return ['ok' => true, 'message' => 'Klasör silindi.'];
        }
        if (!unlink($targetPath)) {
            return ['ok' => false, 'message' => 'Dosya silinemedi.'];
        }
        return ['ok' => true, 'message' => 'Dosya silindi.'];
    }

    public function readFile(string $siteId, string $relativePath): array
    {
        [, $targetPath, ] = $this->resolvePath($siteId, $relativePath);
        if ($targetPath === null || !is_file($targetPath)) {
            return ['ok' => false, 'message' => 'Dosya bulunamadı.'];
        }
        $content = file_get_contents($targetPath);
        if ($content === false) {
            return ['ok' => false, 'message' => 'Dosya okunamadı.'];
        }
        return ['ok' => true, 'content' => $content];
    }

    public function createDirectory(string $siteId, string $parentPath, string $directoryName): array
    {
        $directoryName = trim($directoryName);
        if (!preg_match('/^[a-zA-Z0-9._-]{1,80}$/', $directoryName)) {
            return ['ok' => false, 'message' => 'Klasör adı geçersiz.'];
        }
        [, $targetPath, ] = $this->resolvePath($siteId, ltrim($parentPath . '/' . $directoryName, '/'));
        if ($targetPath === null) {
            return ['ok' => false, 'message' => 'Klasör yolu geçersiz.'];
        }
        if (file_exists($targetPath)) {
            return ['ok' => false, 'message' => 'Aynı isimde dosya veya klasör var.'];
        }
        if (!mkdir($targetPath, 0755, true) && !is_dir($targetPath)) {
            return ['ok' => false, 'message' => 'Klasör oluşturulamadı.'];
        }
        return ['ok' => true, 'message' => 'Klasör oluşturuldu.'];
    }

    public function renamePath(string $siteId, string $sourcePath, string $newName): array
    {
        $newName = trim($newName);
        if (!preg_match('/^[a-zA-Z0-9._-]{1,120}$/', $newName)) {
            return ['ok' => false, 'message' => 'Yeni ad geçersiz.'];
        }
        [, $sourceTarget, $sourceRelative] = $this->resolvePath($siteId, $sourcePath);
        if ($sourceTarget === null || !file_exists($sourceTarget)) {
            return ['ok' => false, 'message' => 'Kaynak bulunamadı.'];
        }

        $parent = dirname($sourceRelative);
        $parent = $parent === '.' ? '' : $parent;
        [, $destinationTarget, ] = $this->resolvePath($siteId, ltrim($parent . '/' . $newName, '/'));
        if ($destinationTarget === null) {
            return ['ok' => false, 'message' => 'Hedef yol geçersiz.'];
        }
        if (file_exists($destinationTarget)) {
            return ['ok' => false, 'message' => 'Bu adda başka bir öğe var.'];
        }
        if (!rename($sourceTarget, $destinationTarget)) {
            return ['ok' => false, 'message' => 'Yeniden adlandırma başarısız.'];
        }
        return ['ok' => true, 'message' => 'Ad güncellendi.'];
    }

    public function movePath(string $siteId, string $sourcePath, string $destinationPath): array
    {
        [, $sourceTarget, ] = $this->resolvePath($siteId, $sourcePath);
        [, $destinationTarget, ] = $this->resolvePath($siteId, $destinationPath);
        if ($sourceTarget === null || !file_exists($sourceTarget)) {
            return ['ok' => false, 'message' => 'Taşınacak kaynak bulunamadı.'];
        }
        if ($destinationTarget === null) {
            return ['ok' => false, 'message' => 'Hedef yol geçersiz.'];
        }
        if (file_exists($destinationTarget)) {
            return ['ok' => false, 'message' => 'Hedefte aynı isimde öğe var.'];
        }
        $dir = dirname($destinationTarget);
        if (!is_dir($dir) && !mkdir($concurrentDirectory = $dir, 0755, true) && !is_dir($concurrentDirectory)) {
            return ['ok' => false, 'message' => 'Hedef dizin oluşturulamadı.'];
        }
        if (!rename($sourceTarget, $destinationTarget)) {
            return ['ok' => false, 'message' => 'Taşıma başarısız.'];
        }
        return ['ok' => true, 'message' => 'Öğe taşındı.'];
    }

    public function copyPath(string $siteId, string $sourcePath, string $destinationPath): array
    {
        [, $sourceTarget, ] = $this->resolvePath($siteId, $sourcePath);
        [, $destinationTarget, ] = $this->resolvePath($siteId, $destinationPath);
        if ($sourceTarget === null || !file_exists($sourceTarget)) {
            return ['ok' => false, 'message' => 'Kopyalanacak kaynak bulunamadı.'];
        }
        if ($destinationTarget === null) {
            return ['ok' => false, 'message' => 'Hedef yol geçersiz.'];
        }
        if (file_exists($destinationTarget)) {
            return ['ok' => false, 'message' => 'Hedefte aynı isimde öğe var.'];
        }
        $dir = dirname($destinationTarget);
        if (!is_dir($dir) && !mkdir($concurrentDirectory = $dir, 0755, true) && !is_dir($concurrentDirectory)) {
            return ['ok' => false, 'message' => 'Hedef dizin oluşturulamadı.'];
        }
        if (is_dir($sourceTarget)) {
            return ['ok' => false, 'message' => 'Klasör kopyalama bu adımda desteklenmiyor.'];
        }
        if (!copy($sourceTarget, $destinationTarget)) {
            return ['ok' => false, 'message' => 'Kopyalama başarısız.'];
        }
        return ['ok' => true, 'message' => 'Dosya kopyalandı.'];
    }

    /**
     * @param array<int, string> $paths
     */
    public function bulkDelete(string $siteId, array $paths): array
    {
        $deleted = 0;
        $errors = [];
        foreach ($paths as $path) {
            $result = $this->deletePath($siteId, (string) $path);
            if (($result['ok'] ?? false) === true) {
                $deleted++;
                continue;
            }
            $errors[] = (string) ($path . ': ' . ($result['message'] ?? 'silinemedi'));
        }
        if ($deleted === 0) {
            return ['ok' => false, 'message' => 'Toplu silmede hiçbir öğe silinemedi.', 'errors' => $errors];
        }
        if ($errors !== []) {
            return ['ok' => true, 'message' => $deleted . ' öğe silindi, bazı öğeler silinemedi.', 'errors' => $errors];
        }
        return ['ok' => true, 'message' => $deleted . ' öğe silindi.'];
    }

    /**
     * @param array<string, mixed> $file
     * @return array<string, mixed>
     */
    public function uploadFile(string $siteId, string $currentPath, array $file): array
    {
        $name = trim((string) ($file['name'] ?? ''));
        $tmpName = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'message' => 'Yükleme hatası oluştu.'];
        }
        if ($name === '' || $tmpName === '') {
            return ['ok' => false, 'message' => 'Yüklenecek dosya bulunamadı.'];
        }
        $maxUploadMb = $this->maxUploadMb();
        if ($size <= 0 || $size > ($maxUploadMb * 1024 * 1024)) {
            return ['ok' => false, 'message' => 'Dosya boyutu ' . $maxUploadMb . 'MB sınırını aşıyor veya geçersiz.'];
        }
        if (!$this->isSafeFilename($name)) {
            return ['ok' => false, 'message' => 'Dosya adı güvenlik kuralına uymuyor.'];
        }

        [, $targetPath, ] = $this->resolvePath($siteId, ltrim($currentPath . '/' . basename($name), '/'));
        if ($targetPath === null) {
            return ['ok' => false, 'message' => 'Yükleme hedef yolu geçersiz.'];
        }
        $dir = dirname($targetPath);
        if (!is_dir($dir) && !mkdir($concurrentDirectory = $dir, 0755, true) && !is_dir($concurrentDirectory)) {
            return ['ok' => false, 'message' => 'Yükleme dizini oluşturulamadı.'];
        }
        if (!is_uploaded_file($tmpName)) {
            return ['ok' => false, 'message' => 'Yükleme kaynağı doğrulanamadı.'];
        }
        if (!move_uploaded_file($tmpName, $targetPath)) {
            return ['ok' => false, 'message' => 'Dosya sunucuya taşınamadı.'];
        }
        return ['ok' => true, 'message' => 'Dosya yüklendi.', 'path' => ltrim($currentPath . '/' . basename($name), '/')];
    }

    /**
     * @return array<string, mixed>
     */
    public function downloadFile(string $siteId, string $relativePath): array
    {
        [, $targetPath, ] = $this->resolvePath($siteId, $relativePath);
        if ($targetPath === null || !is_file($targetPath)) {
            return ['ok' => false, 'message' => 'İndirilecek dosya bulunamadı.'];
        }
        $content = file_get_contents($targetPath);
        if ($content === false) {
            return ['ok' => false, 'message' => 'Dosya okunamadı.'];
        }
        $mime = function_exists('mime_content_type') ? (string) (@mime_content_type($targetPath) ?: 'application/octet-stream') : 'application/octet-stream';
        return [
            'ok' => true,
            'filename' => basename($targetPath),
            'content' => $content,
            'mime' => $mime,
            'size' => filesize($targetPath) ?: strlen($content),
        ];
    }

    /**
     * @return array{0:?string,1:?string,2:string}
     */
    private function resolvePath(string $siteId, string $relativePath): array
    {
        $site = $this->siteService->findSite($siteId);
        if ($site === null) {
            return [null, null, ''];
        }

        $domain = (string) ($site['domain'] ?? '');
        $basePath = AILHOST_ROOT . '/var/site_files/' . str_replace('.', '_', $domain);
        if (!is_dir($basePath) && !mkdir($concurrentDirectory = $basePath, 0755, true) && !is_dir($concurrentDirectory)) {
            return [null, null, ''];
        }

        $relativePath = trim(str_replace('\\', '/', $relativePath));
        $relativePath = ltrim($relativePath, '/');
        if ($relativePath !== '' && str_contains($relativePath, '..')) {
            return [null, null, ''];
        }

        $targetPath = $basePath . ($relativePath === '' ? '' : '/' . $relativePath);
        return [$basePath, $targetPath, $relativePath];
    }

    private function parentPath(string $relativePath): string
    {
        if ($relativePath === '' || !str_contains($relativePath, '/')) {
            return '';
        }
        return dirname($relativePath) === '.' ? '' : dirname($relativePath);
    }

    private function permissions(string $path): string
    {
        $perm = @fileperms($path);
        if ($perm === false) {
            return '----';
        }
        return substr(sprintf('%o', $perm), -4);
    }

    private function isSafeFilename(string $name): bool
    {
        if (str_contains($name, '/') || str_contains($name, '\\') || str_contains($name, '..')) {
            return false;
        }
        return (bool) preg_match('/^[a-zA-Z0-9._-]{1,180}$/', $name);
    }

    public function maxUploadMb(): int
    {
        $file = $this->paths['local_var_path'] . '/settings.json';
        if (!is_file($file)) {
            return self::DEFAULT_MAX_UPLOAD_MB;
        }
        $decoded = json_decode((string) file_get_contents($file), true);
        if (!is_array($decoded)) {
            return self::DEFAULT_MAX_UPLOAD_MB;
        }
        $value = (int) ($decoded['file_upload_max_mb'] ?? self::DEFAULT_MAX_UPLOAD_MB);
        return max(1, min(1024, $value));
    }

}
