<?php

namespace Tests\Feature;

use App\Jobs\FetchProfileJob;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RefetchProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dispatches_fetch_profile_job_on_refetch(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $profile = Profile::factory()->create();

        $this->actingAs($user)
            ->post("/watchlist/{$profile->id}/refetch");

        Queue::assertPushed(FetchProfileJob::class);
    }
}