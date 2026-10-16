<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class LogViewerService
{
    /**
     * مسیر پوشه لاگ‌ها
     */
    protected string $logPath;

    public function __construct()
    {
        $this->logPath = storage_path('logs');
    }

    /**
     * دریافت لیست تمام فایل‌های لاگ موجود
     */
    public function getLogFiles(): array
    {
        if (!File::exists($this->logPath)) {
            return [];
        }

        $files = File::files($this->logPath);
        $logFiles = [];

        foreach ($files as $file) {
            if (Str::endsWith($file->getFilename(), '.log')) {
                $logFiles[] = [
                    'name' => $file->getFilename(),
                    'size' => round($file->getSize() / 1024 / 1024, 2) . ' MB',
                    'modified_at' => date('Y-m-d H:i:s', $file->getMTime()),
                ];
            }
        }

        // مرتب‌سازی بر اساس آخرین زمان تغییر
        usort($logFiles, fn ($a, $b) => strcmp($b['modified_at'], $a['modified_at']));

        return $logFiles;
    }

    public function clearLogFile(string $filename): void
    {
        $allowedFiles = array_column($this->getLogFiles(), 'name');
        $filePath = $this->logPath . '/' . $filename;

        if (!in_array($filename, $allowedFiles, true) || is_link($filePath) || !File::isFile($filePath)) {
            throw new \InvalidArgumentException('Invalid log file.');
        }

        if (File::put($filePath, '', true) === false) {
            throw new \RuntimeException('Unable to clear log file.');
        }
    }

    /**
     * پارس کردن و دریافت آیتم‌های لاگ یک فایل مشخص
     */
    public function getParsedLogs(string $filename = 'laravel.log', array $filters = []): array
    {
        $filePath = $this->logPath . '/' . $filename;

        if (!File::exists($filePath)) {
            return [
                'file' => $filename,
                'total' => 0,
                'logs' => [],
            ];
        }

        // اگر فایل خیلی بزرگ بود، فقط ۵ مگابایت آخر را می‌خوانیم تا حافظه پر نشود
        $content = $this->readLastBytes($filePath, 5 * 1024 * 1024);

        $pattern = '/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[\+-]\d{2}:\d{2})?)\]\s+([a-zA-Z0-9_\-]+)\.([A-Z]+):\s+(.*?)(?=\n\[\d{4}-\d{2}-\d{2}|\z)/ms';

        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        $parsedLogs = [];

        foreach ($matches as $match) {
            $timestamp = $match[1];
            $environment = $match[2];
            $level = strtoupper($match[3]);
            $rawMessage = trim($match[4]);

            // جدا کردن پیام اصلی از Stack Trace
            $messageParts = explode("\n[stacktrace]\n", $rawMessage, 2);
            $message = $messageParts[0];
            $stackTrace = $messageParts[1] ?? null;

            // اعمال فیلتر سطح لاگ (مثلاً فقط ERROR یا INFO)
            if (!empty($filters['level']) && strcasecmp($filters['level'], $level) !== 0) {
                continue;
            }

            // اعمال فیلتر جستجوی متنی
            if (!empty($filters['search'])) {
                $search = strtolower($filters['search']);
                if (!Str::contains(strtolower($message), $search) && !Str::contains(strtolower($stackTrace ?? ''), $search)) {
                    continue;
                }
            }

            $parsedLogs[] = [
                'timestamp'   => $timestamp,
                'environment' => $environment,
                'level'       => $level,
                'message'     => $message,
                'stack_trace' => $stackTrace,
            ];
        }

        // لاگ‌های جدیدتر بالا قرار بگیرند
        $parsedLogs = array_reverse($parsedLogs);

        // پجینیشن دستی
        $perPage = $filters['per_page'] ?? 50;
        $page = $filters['page'] ?? 1;
        $offset = ($page - 1) * $perPage;

        return [
            'file'        => $filename,
            'total'       => count($parsedLogs),
            'page'        => (int) $page,
            'per_page'    => (int) $perPage,
            'last_page'   => (int) ceil(count($parsedLogs) / $perPage),
            'logs'        => array_slice($parsedLogs, $offset, $perPage),
        ];
    }

    /**
     * خواندن بایت‌های آخر فایل برای مدیریت حافظه در لاگ‌های حجیم
     */
    protected function readLastBytes(string $filePath, int $bytes): string
    {
        $handle = fopen($filePath, 'r');
        $size = filesize($filePath);

        if ($size > $bytes) {
            fseek($handle, -$bytes, SEEK_END);
            // خط اول ناقص را نادیده می‌گیریم
            fgets($handle);
        }

        $output = stream_get_contents($handle);
        fclose($handle);

        return $output;
    }
}
