<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Bale\BaleLinkTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class BaleConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'bale.token' => 'testing-token',
            'bale.bot_username' => 'TestBot',
            'bale.webhook_secret' => 'testing-webhook-secret',
            'bale.link_token_ttl_seconds' => 300,
            'bale.redis_prefix' => 'test:bale:'.bin2hex(random_bytes(8)).':',
        ]);

        Http::fake(['tapi.bale.ai/*' => Http::response(['ok' => true, 'result' => true])]);
    }

    public function test_link_issues_a_redis_token_and_webhook_connects_user_once(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $link = $this->postJson('/api/v1/bale/link')
            ->assertOk()
            ->assertJsonPath('connected', false)
            ->assertJsonPath('expires_in', 300)
            ->json('deep_link');

        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);
        $token = $query['start'];
        $key = config('bale.redis_prefix').$token;

        $this->assertSame((string) $user->id, Redis::connection()->get($key));
        $this->assertGreaterThan(0, Redis::connection()->ttl($key));

        $payload = [
            'update_id' => 123,
            'message' => [
                'message_id' => 456,
                'text' => '/start '.$token,
                'chat' => ['id' => 987654321, 'type' => 'private'],
                'from' => ['id' => 987654321, 'username' => 'bale_student'],
            ],
        ];

        $this->postJson('/api/v1/bale/webhook?secret=wrong', $payload)->assertForbidden();
        $this->assertSame((string) $user->id, Redis::connection()->get($key));

        $this->postJson('/api/v1/bale/webhook?secret=testing-webhook-secret', $payload)
            ->assertOk()
            ->assertJsonPath('connected', true);

        $this->assertSame('987654321', $user->fresh()->bale_chat_id);
        $this->assertSame('bale_student', $user->fresh()->bale_username);
        $this->assertNull(Redis::connection()->get($key));

        $this->postJson('/api/v1/bale/webhook?secret=testing-webhook-secret', $payload)
            ->assertStatus(422);

        $this->postJson('/api/v1/bale/link')->assertOk()->assertJsonPath('connected', true);
        Http::assertSentCount(2);
    }

    public function test_latest_token_only_can_be_used_and_disconnect_clears_connection(): void
    {
        $user = User::factory()->create();
        $tokens = app(BaleLinkTokenService::class);
        $first = $tokens->issueTokenForUser($user->id);
        $second = $tokens->issueTokenForUser($user->id);

        $this->assertNull($tokens->consumeToken($first['token']));
        $this->assertSame($user->id, $tokens->consumeToken($second['token']));
        $this->assertNull($tokens->consumeToken($second['token']));

        $user->update(['bale_chat_id' => '123', 'bale_username' => 'student']);
        $this->actingAs($user)->deleteJson('/api/v1/bale/connection')
            ->assertOk()
            ->assertJsonPath('connected', false);

        $this->assertNull($user->fresh()->bale_chat_id);
        $this->assertNull($user->fresh()->bale_username);
    }

    public function test_private_chat_is_required_before_consuming_token(): void
    {
        $user = User::factory()->create();
        $token = app(BaleLinkTokenService::class)->issueTokenForUser($user->id)['token'];

        $this->postJson('/api/v1/bale/webhook?secret=testing-webhook-secret', [
            'message' => [
                'text' => '/start '.$token,
                'chat' => ['id' => -12345, 'type' => 'group'],
            ],
        ])->assertOk();

        $this->assertSame((string) $user->id, Redis::connection()->get(config('bale.redis_prefix').$token));
        $this->assertNull($user->fresh()->bale_chat_id);
    }

    public function test_link_requires_authentication_and_expired_token_cannot_connect(): void
    {
        $this->postJson('/api/v1/bale/link')->assertUnauthorized();
        $this->deleteJson('/api/v1/bale/connection')->assertUnauthorized();

        $user = User::factory()->create();
        $token = app(BaleLinkTokenService::class)->issueTokenForUser($user->id)['token'];
        Redis::connection()->del(config('bale.redis_prefix').$token);

        $this->postJson('/api/v1/bale/webhook', [
            'message' => [
                'text' => '/start '.$token,
                'chat' => ['id' => 123456, 'type' => 'private'],
            ],
        ])->assertForbidden();

        $this->postJson('/api/v1/bale/webhook?secret=testing-webhook-secret', [
            'message' => [
                'text' => '/start '.$token,
                'chat' => ['id' => 123456, 'type' => 'private'],
            ],
        ])->assertStatus(422);

        $this->assertNull($user->fresh()->bale_chat_id);
    }
}
