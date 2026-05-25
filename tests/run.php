<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Ailhost\Core\Request;
use Ailhost\Core\Response;
use Ailhost\Core\Router;
use Ailhost\Core\View;
use Ailhost\Repositories\JsonJobRepository;
use Ailhost\Services\JsonStateStore;
use Ailhost\Services\JobService;
use Ailhost\Services\RateLimiterService;
use Ailhost\Tests\Support\TestCase;

/**
 * @return array<string, callable(): void>
 */
function ailhost_tests(): array
{
    return [
        'php syntax' => static function (): void {
            $root = AILHOST_ROOT;
            $paths = [
                $root . '/app',
                $root . '/bootstrap',
                $root . '/config',
                $root . '/public',
                $root . '/resources/views',
                $root . '/scripts',
                $root . '/tests',
            ];

            foreach (phpFiles($paths) as $file) {
                $command = 'php -l ' . escapeshellarg($file) . ' 2>&1';
                $output = [];
                $exitCode = 0;
                exec($command, $output, $exitCode);
                TestCase::assertSame(0, $exitCode, 'PHP syntax hatası: ' . $file . "\n" . implode("\n", $output));
            }
        },
        'router smoke' => static function (): void {
            $router = new Router();
            $router->get('/smoke', static fn(Request $request): Response => new Response('ok:' . $request->path()));

            $content = responseContent($router->dispatch(new Request('GET', '/smoke')));

            TestCase::assertSame('ok:/smoke', $content, 'Router GET route response hatalı.');
        },
        'job service lifecycle' => static function (): void {
            $tempDir = TestCase::tempDir('jobs');
            try {
                $service = new JobService(new JsonJobRepository($tempDir));
                $result = $service->enqueue('test_job', ['site_id' => 'site-1']);

                TestCase::assertTrue((bool) ($result['ok'] ?? false), 'Job kuyruğa alınamadı.');
                TestCase::assertSame(1, count($service->all()), 'Job sayısı hatalı.');

                $claimed = $service->claimNext();
                TestCase::assertTrue(is_array($claimed), 'Pending job claim edilemedi.');
                TestCase::assertSame('running', (string) ($claimed['status'] ?? ''), 'Claim edilen job running olmalı.');

                $jobId = (string) ($claimed['id'] ?? '');
                TestCase::assertTrue($service->complete($jobId), 'Job tamamlanamadı.');
                TestCase::assertSame('done', (string) (($service->all()[0]['status'] ?? '')), 'Job status done olmalı.');
                TestCase::assertTrue(count($service->logs()) >= 3, 'Job logları yazılmalı.');
            } finally {
                TestCase::removeDir($tempDir);
            }
        },
        'rate limiter buckets' => static function (): void {
            $tempDir = TestCase::tempDir('rate-limit');
            try {
                $limiter = new RateLimiterService($tempDir);
                $buckets = [
                    'global' => ['max_attempts' => 2, 'window_seconds' => 60, 'lock_seconds' => 120],
                    'ip:127.0.0.1' => ['max_attempts' => 2, 'window_seconds' => 60, 'lock_seconds' => 120],
                ];

                $first = $limiter->check($buckets);
                TestCase::assertTrue(($first['locked'] ?? false) === false, 'Baslangicta kilit olmamali.');

                $limiter->hit($buckets);
                $second = $limiter->check($buckets);
                TestCase::assertTrue(($second['locked'] ?? false) === false, 'Ilk denemede kilit olmamali.');

                $limiter->hit($buckets);
                $third = $limiter->check($buckets);
                TestCase::assertTrue(($third['locked'] ?? false) === true, 'Limit asiminda kilit beklenir.');
                TestCase::assertTrue(((int) ($third['retry_after'] ?? 0)) > 0, 'Retry-after pozitif olmali.');

                $limiter->clear(['global', 'ip:127.0.0.1']);
                $afterClear = $limiter->check($buckets);
                TestCase::assertTrue(($afterClear['locked'] ?? false) === false, 'Clear sonrasi kilit kalkmali.');
            } finally {
                TestCase::removeDir($tempDir);
            }
        },
        'json state store atomic write' => static function (): void {
            $tempDir = TestCase::tempDir('json-store');
            try {
                $store = new JsonStateStore();
                $file = $tempDir . '/state.json';

                $ok = $store->writeArray($file, ['a' => 1, 'b' => ['k' => 'v']]);
                TestCase::assertTrue($ok, 'JSON state write basarisiz.');

                $read = $store->readArray($file);
                TestCase::assertSame(1, (int) ($read['a'] ?? 0), 'JSON state read hatali.');
                TestCase::assertSame('v', (string) (($read['b']['k'] ?? '')), 'Nested JSON state read hatali.');
            } finally {
                TestCase::removeDir($tempDir);
            }
        },
        'dashboard view smoke' => static function (): void {
            $html = View::render('dashboard/index', [
                'title' => 'Başlangıç Merkezi',
                'totalSites' => 1,
                'activeSites' => 1,
                'latestBackup' => 'yok',
                'serverIp' => '127.0.0.1',
                'primarySiteId' => 'site-1',
                'primarySiteDomain' => 'example.com',
                'jobStatusCounts' => [
                    'pending' => 0,
                    'running' => 0,
                    'done' => 0,
                    'failed' => 0,
                ],
                'recentActivities' => [],
                'topbarJobSummary' => [
                    'pending' => 0,
                    'running' => 0,
                    'failed' => 0,
                ],
                'authEmail' => 'admin@example.com',
                'authRole' => 'admin',
                'csrfToken' => 'test-token',
                'toastMessage' => '',
                'toastType' => 'info',
                'toastPersistent' => false,
            ]);

            TestCase::assertContains('Başlangıç Merkezi', $html, 'Dashboard başlığı render edilmedi.');
            TestCase::assertContains('Hızlı Başlangıç', $html, 'Dashboard hızlı başlangıç alanı render edilmedi.');
            TestCase::assertContains('/sites/deploy?site=site-1', $html, 'Dashboard deploy kısa yolu site bağlamı taşımıyor.');
        },
    ];
}

/**
 * @param array<int, string> $paths
 * @return array<int, string>
 */
function phpFiles(array $paths): array
{
    $files = [];
    foreach ($paths as $path) {
        if (is_file($path) && str_ends_with($path, '.php')) {
            $files[] = $path;
            continue;
        }

        if (!is_dir($path)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof SplFileInfo || $fileInfo->getExtension() !== 'php') {
                continue;
            }
            $files[] = $fileInfo->getPathname();
        }
    }

    sort($files);
    return $files;
}

function responseContent(Response $response): string
{
    $reflection = new ReflectionClass($response);
    $property = $reflection->getProperty('content');
    $value = $property->getValue($response);
    return is_string($value) ? $value : '';
}

$failed = 0;
foreach (ailhost_tests() as $name => $test) {
    try {
        $test();
        echo "[OK] " . $name . PHP_EOL;
    } catch (Throwable $exception) {
        $failed++;
        echo "[FAIL] " . $name . PHP_EOL;
        echo $exception->getMessage() . PHP_EOL;
    }
}

if ($failed > 0) {
    exit(1);
}

echo "Tüm testler geçti." . PHP_EOL;
