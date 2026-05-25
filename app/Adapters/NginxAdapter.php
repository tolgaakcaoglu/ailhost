<?php

declare(strict_types=1);

namespace Ailhost\Adapters;

final class NginxAdapter implements SystemAdapterInterface
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    public function execute(array $payload): array
    {
        $action = (string) ($payload['action'] ?? '');
        $allowed = ['reload', 'test_config', 'create_site_config', 'delete_site_config', 'service_health'];

        if (!in_array($action, $allowed, true)) {
            return ['ok' => false, 'message' => 'Desteklenmeyen Nginx işlemi.'];
        }

        return match ($action) {
            'service_health' => $this->serviceHealth(),
            'create_site_config' => $this->createSiteConfig($payload),
            'delete_site_config' => $this->deleteSiteConfig($payload),
            'test_config' => $this->testConfig($payload),
            'reload' => ['ok' => true, 'message' => 'Nginx reload planlandı.'],
            default => ['ok' => false, 'message' => 'Desteklenmeyen Nginx işlemi.'],
        };
    }

    private function createSiteConfig(array $payload): array
    {
        $domain = mb_strtolower(trim((string) ($payload['domain'] ?? '')));
        $documentRoot = trim((string) ($payload['document_root'] ?? ''));

        if (!$this->isValidDomain($domain)) {
            return ['ok' => false, 'message' => 'Geçersiz domain.'];
        }
        if (!$this->isValidDocumentRoot($documentRoot)) {
            return ['ok' => false, 'message' => 'Geçersiz document root.'];
        }

        $templateFile = $this->projectRoot . '/templates/nginx-static-site.conf.template';
        if (!is_file($templateFile)) {
            return ['ok' => false, 'message' => 'Nginx template bulunamadı.'];
        }

        $template = (string) file_get_contents($templateFile);
        $content = str_replace(
            ['{{DOMAIN}}', 'root /var/www/{{DOMAIN}}/public_html;'],
            [$domain, 'root ' . $documentRoot . ';'],
            $template
        );
        $proxyRoutes = is_array($payload['proxy_routes'] ?? null) ? $payload['proxy_routes'] : [];
        $proxyBlocks = $this->renderProxyLocations($proxyRoutes);
        if ($proxyBlocks !== '') {
            $content = str_replace("\tlocation / {\n", $proxyBlocks . "\tlocation / {\n", $content);
        }

        $configFile = $this->generatedDir() . '/' . $domain . '.conf';
        if (!is_dir($this->generatedDir()) && !mkdir($concurrentDirectory = $this->generatedDir(), 0755, true) && !is_dir($concurrentDirectory)) {
            return ['ok' => false, 'message' => 'Nginx çıktı dizini oluşturulamadı.'];
        }

        if (file_put_contents($configFile, $content) === false) {
            return ['ok' => false, 'message' => 'Nginx config yazılamadı.'];
        }

        return ['ok' => true, 'message' => 'Nginx config üretildi.', 'config_file' => $configFile];
    }

    /**
     * @return array<string, mixed>
     */
    private function serviceHealth(): array
    {
        $binary = trim((string) @shell_exec('command -v nginx 2>/dev/null'));
        if ($binary === '') {
            return ['ok' => false, 'status' => 'not_installed', 'service' => 'nginx', 'version' => '-'];
        }

        $active = trim((string) @shell_exec('systemctl is-active nginx 2>/dev/null'));
        if ($active === '') {
            $active = trim((string) @shell_exec('pgrep -x nginx >/dev/null 2>&1 && echo running || echo stopped'));
        }

        $versionOutput = trim((string) @shell_exec('nginx -v 2>&1'));
        $version = $versionOutput !== '' ? preg_replace('/^nginx version:\s*/', '', $versionOutput) : 'nginx';

        return [
            'ok' => $active === 'active' || $active === 'running',
            'status' => ($active === 'active' || $active === 'running') ? 'running' : 'stopped',
            'service' => 'nginx',
            'version' => $version,
        ];
    }

    private function testConfig(array $payload): array
    {
        $configFile = (string) ($payload['config_file'] ?? '');
        if ($configFile === '' || !is_file($configFile)) {
            return ['ok' => false, 'message' => 'Config dosyası bulunamadı.'];
        }

        $content = (string) file_get_contents($configFile);
        $open = substr_count($content, '{');
        $close = substr_count($content, '}');
        if ($open !== $close) {
            return ['ok' => false, 'message' => 'Nginx config blok dengesi hatalı.'];
        }

        if (!str_contains($content, 'server_name') || !str_contains($content, 'root ')) {
            return ['ok' => false, 'message' => 'Nginx config içeriği eksik.'];
        }

        return ['ok' => true, 'message' => 'Nginx config doğrulandı.'];
    }

    private function generatedDir(): string
    {
        return $this->projectRoot . '/var/generated/nginx';
    }

    private function deleteSiteConfig(array $payload): array
    {
        $domain = mb_strtolower(trim((string) ($payload['domain'] ?? '')));
        if ($domain === '') {
            return ['ok' => false, 'message' => 'Domain gerekli.'];
        }

        $configFile = $this->generatedDir() . '/' . $domain . '.conf';
        if (!is_file($configFile)) {
            return ['ok' => true, 'message' => 'Nginx config zaten yok.'];
        }

        return unlink($configFile)
            ? ['ok' => true, 'message' => 'Nginx config silindi.']
            : ['ok' => false, 'message' => 'Nginx config silinemedi.'];
    }

    private function isValidDomain(string $domain): bool
    {
        return (bool) preg_match('/^(?=.{1,253}$)(?!-)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain);
    }

    private function isValidDocumentRoot(string $documentRoot): bool
    {
        if (!str_starts_with($documentRoot, '/var/www/')) {
            return false;
        }

        return !str_contains($documentRoot, '..');
    }

    /**
     * @param array<int, array<string, mixed>> $routes
     */
    private function renderProxyLocations(array $routes): string
    {
        if ($routes === []) {
            return '';
        }

        $blocks = '';
        foreach ($routes as $route) {
            $prefix = trim((string) ($route['prefix'] ?? ''));
            $targetPort = (int) ($route['target_port'] ?? 0);
            if ($prefix === '' || !str_starts_with($prefix, '/')) {
                continue;
            }
            if (!preg_match('#^/[a-zA-Z0-9/_-]{0,120}$#', $prefix)) {
                continue;
            }
            if ($targetPort < 1 || $targetPort > 65535) {
                continue;
            }
            $blocks .= "\tlocation " . $prefix . " {\n";
            $blocks .= "\t\tproxy_pass http://127.0.0.1:" . $targetPort . ";\n";
            $blocks .= "\t\tproxy_http_version 1.1;\n";
            $blocks .= "\t\tproxy_set_header Host \$host;\n";
            $blocks .= "\t\tproxy_set_header X-Real-IP \$remote_addr;\n";
            $blocks .= "\t\tproxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;\n";
            $blocks .= "\t\tproxy_set_header X-Forwarded-Proto \$scheme;\n";
            $blocks .= "\t}\n\n";
        }

        return $blocks;
    }
}
