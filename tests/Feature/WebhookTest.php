<?php

namespace Tests\Feature;

use App\Models\Profile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_signature_is_rejected(): void
    {
        $response = $this->postJson(
            '/webhooks/youtube',
            [
                'channel_id' => 'abc123',
            ],
            [
                'X-Signature' => 'fake-signature',
                'X-Event-Id' => 'event-1',
            ]
        );

        $response->assertStatus(401);
    }

    public function test_duplicate_webhook_event_is_rejected(): void
    {
        Redis::set(
            'webhook_event:event-123',
            1,
            'EX',
            300
        );

        $payload = [
            'channel_id' => 'abc123',
        ];

        $signature = hash_hmac(
            'sha256',
            json_encode($payload),
            config('services.youtube_webhook.secret')
        );

        $response = $this->postJson(
            '/webhooks/youtube',
            $payload,
            [
                'X-Signature' => $signature,
                'X-Event-Id' => 'event-123',
            ]
        );

        $response->assertStatus(409);
    }
}