<?php
namespace App\Checks;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Illuminate\Support\Facades\DB;

class DatabaseInfoCheck extends Check
{
    public ?string $name = 'Database';
    public ?string $label = 'Database';

    public function run(): Result
    {
        try {
            $pdo = DB::connection()->getPdo();
            $version = DB::select("SELECT VERSION() as version")[0]->version ?? 'Unknown';
            $dbName = DB::connection()->getDatabaseName();

            // محاسبه حجم تقریبی دیتابیس
            $sizeRow = DB::select("
                SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                FROM information_schema.TABLES
                WHERE table_schema = ?
            ", [$dbName]);

            $sizeMb = $sizeRow[0]->size_mb ?? 0;

            return Result::make()
                ->ok()
                ->shortSummary("متصل ({$version})")
                ->meta([
                    'connection_name' => DB::getDefaultConnection(),
                    'database_name'   => $dbName,
                    'version'         => $version,
                    'size_mb'         => "{$sizeMb} MB",
                ]);
        } catch (\Throwable $e) {
            return Result::make()->failed("خطا در اتصال: {$e->getMessage()}");
        }
    }
}
