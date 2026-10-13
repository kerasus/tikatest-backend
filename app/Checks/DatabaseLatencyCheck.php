<?php
namespace App\Checks;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Illuminate\Support\Facades\DB;

class DatabaseLatencyCheck extends Check
{
    public ?string $name = 'DatabasePing';
    public ?string $label = 'Database Ping & Latency';

    public function run(): Result
    {
        $start = microtime(true);
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $latency = round((microtime(true) - $start) * 1000, 2);

            $result = Result::make()
                ->ok()
                ->shortSummary("{$latency} ms")
                ->meta(['latency_ms' => $latency]);

            if ($latency > 150) {
                return $result->warning("پینگ دیتابیس بالاست: {$latency}ms");
            }

            return $result;
        } catch (\Throwable $e) {
            return Result::make()
                ->failed("عدم برقراری ارتباط: {$e->getMessage()}");
        }
    }
}
