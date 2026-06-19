<?php

namespace App\Modules\Backup\Services;

use App\Services\SettingsStore;

class BackupStatusService
{
    protected const STATUS_KEY = 'backup_manual_status';

    protected const STALE_SECONDS = 900;

    public function __construct(protected SettingsStore $settings) {}

    public function startManual(): void
    {
        $this->write([
            'status' => 'running',
            'started_at' => time(),
        ]);
    }

    /** @param  array<string, mixed>  $data */
    public function markDone(array $data = []): void
    {
        $st = $this->read();
        $this->write([
            'status' => 'done',
            'started_at' => (int) ($st['started_at'] ?? time()),
            'finished_at' => time(),
            'ok' => true,
            'data' => $data,
            'message' => (string) ($data['message'] ?? 'بکاپ با موفقیت ساخته شد.'),
        ]);
    }

    public function markFailed(string $message, string $code = 'build_failed'): void
    {
        $st = $this->read();
        $this->write([
            'status' => 'error',
            'started_at' => (int) ($st['started_at'] ?? time()),
            'finished_at' => time(),
            'ok' => false,
            'code' => $code,
            'message' => $message,
        ]);
    }

    public function resetStuck(): array
    {
        $this->settings->set(self::STATUS_KEY, null);

        return ['ok' => true, 'status' => 'idle'];
    }

    public function resetStale(): bool
    {
        $st = $this->read();
        if (($st['status'] ?? '') !== 'running') {
            return false;
        }
        $started = (int) ($st['started_at'] ?? 0);
        if ($started > 0 && (time() - $started) <= self::STALE_SECONDS) {
            return false;
        }
        $this->settings->set(self::STATUS_KEY, null);

        return true;
    }

    /** @return array<string, mixed> */
    public function getStatus(): array
    {
        $this->resetStale();
        $st = $this->read();
        $status = (string) ($st['status'] ?? 'idle');

        if ($status === 'running') {
            return [
                'ok' => true,
                'status' => 'running',
                'message' => 'بکاپ در حال اجراست…',
                'started_at' => (int) ($st['started_at'] ?? 0),
            ];
        }

        if ($status === 'done' || $status === 'error') {
            return [
                'ok' => ! empty($st['ok']),
                'status' => $status,
                'code' => (string) ($st['code'] ?? ''),
                'message' => (string) ($st['message'] ?? ''),
                'data' => is_array($st['data'] ?? null) ? $st['data'] : null,
                'finished_at' => (int) ($st['finished_at'] ?? 0),
            ];
        }

        return ['ok' => true, 'status' => 'idle'];
    }

    public function isRunning(): bool
    {
        $this->resetStale();

        return ($this->read()['status'] ?? '') === 'running';
    }

    /** @return array<string, mixed> */
    protected function read(): array
    {
        $raw = $this->settings->get(self::STATUS_KEY);
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($raw) ? $raw : [];
    }

    /** @param  array<string, mixed>  $state */
    protected function write(array $state): void
    {
        $this->settings->set(self::STATUS_KEY, $state);
    }
}
