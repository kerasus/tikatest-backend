<?php

namespace Tests\Feature;

use App\Services\LogViewerService;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Tests\TestCase;

class LogViewerServiceTest extends TestCase
{
    public function test_it_clears_only_the_selected_log_file(): void
    {
        $directory = sys_get_temp_dir().'/log-viewer-'.uniqid('', true);
        File::makeDirectory($directory);

        try {
            File::put($directory.'/selected.log', 'old entries');
            File::put($directory.'/other.log', 'keep these entries');

            $service = $this->serviceFor($directory);
            $service->clearLogFile('selected.log');

            $this->assertSame('', File::get($directory.'/selected.log'));
            $this->assertSame('keep these entries', File::get($directory.'/other.log'));
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_it_rejects_paths_outside_the_log_directory(): void
    {
        $directory = sys_get_temp_dir().'/log-viewer-'.uniqid('', true);
        File::makeDirectory($directory);

        try {
            $service = $this->serviceFor($directory);

            $this->expectException(InvalidArgumentException::class);
            $service->clearLogFile('../other.log');
        } finally {
            File::deleteDirectory($directory);
        }
    }

    private function serviceFor(string $directory): LogViewerService
    {
        return new class($directory) extends LogViewerService
        {
            public function __construct(string $directory)
            {
                $this->logPath = $directory;
            }
        };
    }
}
