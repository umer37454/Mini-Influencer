<?php

namespace Tests\Feature;

use App\Jobs\FetchProfileJob;
use App\Models\Profile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class FetchProfileConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_one_http_call_happens_for_same_profile(): void
    {
        Http::fake([
            '*' => Http::response([
                'items' => [[
                    'id' => '123',
                    'snippet' => [
                        'title' => 'Test Channel',
                        'description' => 'Test Bio',
                        'thumbnails' => [
                            'high' => [
                                'url' => 'https://example.com/image.jpg',
                            ],
                        ],
                    ],
                    'statistics' => [
                        'subscriberCount' => 1000,
                        'videoCount' => 50,
                        'viewCount' => 100000,
                    ],
                ]],
            ], 200),
        ]);

        $profile = Profile::factory()->create([
            'username' => 'testchannel',
        ]);

        $job1 = new FetchProfileJob($profile);

        $job1->handle();

        Redis::set(
            "fetch_profile_lock:{$profile->id}",
            1,
            'EX',
            120,
            'NX'
        );

        $job2 = new FetchProfileJob($profile);

        $job2->handle();

        Http::assertSentCount(1);
    }
}