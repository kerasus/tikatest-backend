<?php
namespace App\Checks;

use Throwable;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Spatie\SslCertificate\SslCertificate;

class SslCertificateCheck extends Check
{
    public function run(): Result
    {
        $domain = trim((string) config('health.ssl_domain'));

        if ($domain === '') {
            return Result::make()
                ->failed('دامنه برای بررسی گواهی SSL تنظیم نشده است.');
        }

        // اگر در env آدرس کامل وارد شده باشد، فقط hostname را استخراج می‌کنیم.
        $host = parse_url(
            str_contains($domain, '://') ? $domain : "https://{$domain}",
            PHP_URL_HOST
        );

        if (! is_string($host) || $host === '') {
            return Result::make()
                ->failed('مقدار دامنه SSL معتبر نیست.');
        }

        try {
            $certificate = SslCertificate::createForHostName($host);
            $daysRemaining = $certificate->daysUntilExpirationDate();

            if (! $certificate->isValid()) {
                return Result::make()
                    ->failed("گواهی SSL برای {$host} معتبر نیست.")
                    ->meta(['domain' => $host]);
            }

            if ($daysRemaining <= 10) {
                return Result::make()
                    ->warning("گواهی SSL برای {$host} تا {$daysRemaining} روز دیگر منقضی می‌شود.")
                    ->meta([
                        'domain' => $host,
                        'days_until_expiration' => $daysRemaining,
                    ]);
            }

            return Result::make()
                ->ok()
                ->shortSummary("{$daysRemaining} روز")
                ->meta([
                    'domain' => $host,
                    'days_until_expiration' => $daysRemaining,
                ]);
        } catch (Throwable $exception) {
            return Result::make()
                ->failed("بررسی گواهی SSL برای {$host} ناموفق بود: {$exception->getMessage()}")
                ->meta(['domain' => $host]);
        }
    }
}
