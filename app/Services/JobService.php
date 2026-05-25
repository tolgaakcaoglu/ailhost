<?php

declare(strict_types=1);

namespace Ailhost\Services;

use Ailhost\Repositories\JobRepositoryInterface;

final class JobService
{
    public function __construct(private readonly JobRepositoryInterface $repository)
    {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function enqueue(string $type, array $payload): array
    {
        $job = [
            'id' => bin2hex(random_bytes(8)),
            'type' => $type,
            'status' => 'pending',
            'payload' => $payload,
            'attempts' => 0,
            'max_attempts' => 3,
            'locked_at' => null,
            'started_at' => null,
            'finished_at' => null,
            'error' => null,
            'created_at' => date(DATE_ATOM),
            'updated_at' => date(DATE_ATOM),
        ];

        $jobs = $this->repository->all();
        $jobs[] = $job;
        if (!$this->repository->saveAll($jobs)) {
            return ['ok' => false, 'message' => 'İş kaydı oluşturulamadı.'];
        }

        $this->appendLog($job['id'], 'info', 'İş kuyruğa alındı.');
        $this->kickWorker();
        return ['ok' => true, 'job' => $job];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->repository->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function logs(): array
    {
        return $this->repository->logs();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latest(): ?array
    {
        $jobs = $this->repository->all();
        if ($jobs === []) {
            return null;
        }

        return $jobs[count($jobs) - 1];
    }

    public function retryLastFailed(): array
    {
        $jobs = $this->repository->all();
        for ($i = count($jobs) - 1; $i >= 0; $i--) {
            if (($jobs[$i]['status'] ?? '') !== 'failed') {
                continue;
            }

            $jobs[$i]['status'] = 'pending';
            $jobs[$i]['locked_at'] = null;
            $jobs[$i]['started_at'] = null;
            $jobs[$i]['finished_at'] = null;
            $jobs[$i]['error'] = null;
            $jobs[$i]['updated_at'] = date(DATE_ATOM);

            if (!$this->repository->saveAll($jobs)) {
                return ['ok' => false, 'message' => 'İş tekrar deneme kaydı yazılamadı.'];
            }

            $this->appendLog((string) $jobs[$i]['id'], 'info', 'İş tekrar kuyruğa alındı.');
            return ['ok' => true, 'message' => 'Son başarısız iş tekrar denendi.'];
        }

        return ['ok' => false, 'message' => 'Tekrar denenecek başarısız iş bulunamadı.'];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function claimNext(int $lockTimeoutSeconds = 300): ?array
    {
        $jobs = $this->repository->all();
        $now = time();

        for ($i = 0, $total = count($jobs); $i < $total; $i++) {
            $status = (string) ($jobs[$i]['status'] ?? '');
            $lockedAt = (string) ($jobs[$i]['locked_at'] ?? '');

            $lockExpired = $lockedAt === '' || strtotime($lockedAt) < ($now - $lockTimeoutSeconds);
            if ($status !== 'pending' && !($status === 'running' && $lockExpired)) {
                continue;
            }

            $jobs[$i]['status'] = 'running';
            $jobs[$i]['locked_at'] = date(DATE_ATOM);
            $jobs[$i]['started_at'] = (string) ($jobs[$i]['started_at'] ?? '') !== '' ? $jobs[$i]['started_at'] : date(DATE_ATOM);
            $jobs[$i]['attempts'] = ((int) ($jobs[$i]['attempts'] ?? 0)) + 1;
            $jobs[$i]['updated_at'] = date(DATE_ATOM);

            if (!$this->repository->saveAll($jobs)) {
                return null;
            }

            $this->appendLog((string) $jobs[$i]['id'], 'info', 'İş worker tarafından alındı.');
            return $jobs[$i];
        }

        return null;
    }

    public function complete(string $jobId, string $message = 'İş tamamlandı.'): bool
    {
        return $this->updateResult($jobId, 'done', null, $message);
    }

    public function fail(string $jobId, string $error): bool
    {
        return $this->updateResult($jobId, 'failed', $error, 'İş başarısız oldu.');
    }

    public function appendLog(string $jobId, string $stream, string $message): void
    {
        $logs = $this->repository->logs();
        $logs[] = [
            'job_id' => $jobId,
            'stream' => $stream,
            'message' => $message,
            'time' => date(DATE_ATOM),
        ];
        $this->repository->saveLogs($logs);
    }

    private function updateResult(string $jobId, string $status, ?string $error, string $logMessage): bool
    {
        $jobs = $this->repository->all();
        for ($i = 0, $total = count($jobs); $i < $total; $i++) {
            if (($jobs[$i]['id'] ?? '') !== $jobId) {
                continue;
            }

            $jobs[$i]['status'] = $status;
            $jobs[$i]['error'] = $error;
            $jobs[$i]['locked_at'] = null;
            $jobs[$i]['finished_at'] = date(DATE_ATOM);
            $jobs[$i]['updated_at'] = date(DATE_ATOM);
            $saved = $this->repository->saveAll($jobs);
            if ($saved) {
                $stream = $status === 'failed' ? 'error' : 'info';
                $this->appendLog($jobId, $stream, $logMessage);
            }
            return $saved;
        }

        return false;
    }

    private function kickWorker(): void
    {
        if (PHP_SAPI === 'cli' || !defined('AILHOST_ROOT')) {
            return;
        }

        $runner = AILHOST_ROOT . '/scripts/worker-runner.php';
        if (!is_file($runner)) {
            return;
        }

        $php = PHP_BINARY !== '' ? PHP_BINARY : 'php';
        $cmd = escapeshellarg($php) . ' ' . escapeshellarg($runner) . ' --drain > /dev/null 2>&1 &';
        @exec($cmd);
    }
}
