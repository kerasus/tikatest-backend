<?php

namespace Tests\Unit;

use App\Http\Middleware\AttachServerTime;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttachServerTimeTest extends TestCase
{
    public function test_api_response_exposes_the_server_time_with_millisecond_precision(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 12:30:45.123', 'UTC'));

        try {
            $response = (new AttachServerTime)->handle(
                Request::create('/api/example'),
                fn () => response()->json(['ok' => true])
            );

            $this->assertSame('2026-10-22T12:30:45.123+00:00', $response->headers->get('X-Server-Time'));
            $this->assertContains('X-Server-Time', config('cors.exposed_headers'));
        } finally {
            Carbon::setTestNow();
        }
    }
}
