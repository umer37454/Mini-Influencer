<?php

namespace App\Jobs;

use App\Models\Profile;
use App\Models\ProfileSnapshot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class FetchProfileJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 60;

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function __construct(public readonly Profile $profile) {}

    public function handle(): void
    {
        $startTime = now();

        $circuitOpenUntil = Redis::get('youtube_circuit_open_until');

        if ($circuitOpenUntil && now()->timestamp < $circuitOpenUntil) {
            Log::warning('Circuit breaker open — delaying job', [
                'profile_id' => $this->profile->id,
            ]);

            $this->release(120);
            return;
        }

        $lockKey = "fetch_profile_lock:{$this->profile->id}";

        $lock = Redis::set($lockKey, 1, 'EX', 120, 'NX');

        if (! $lock) {

            Log::warning('FetchProfileJob skipped — already running', [
                'profile_id' => $this->profile->id,
            ]);

            return;
        }

        try {

            $this->profile->update([
                'status' => 'fetching',
            ]);

            $response = Http::timeout(30)
                ->connectTimeout(3)
                ->withoutVerifying()
                ->get('https://www.googleapis.com/youtube/v3/channels', [
                    'part'      => 'snippet,statistics',
                    'forHandle' => $this->profile->username,
                    'key'       => config('services.youtube.api_key'),
                ]);

            if ($response->status() === 404) {
                $this->profile->update([
                    'status' => 'failed',
                    'error_message' => 'Channel not found',
                ]);

                return;
            }

            if ($response->status() === 401) {
                $this->profile->update([
                    'status' => 'failed',
                    'error_message' => 'Invalid API key',
                ]);

                return;
            }

            if (in_array($response->status(), [429, 500, 502, 503, 504])) {
                throw new \RuntimeException(
                    "Retriable API error: {$response->status()}"
                );
            }

            $data  = $response->json();
            $items = $data['items'] ?? [];

            if (empty($items)) {

                $this->profile->update([
                    'status' => 'failed',
                    'error_message' => 'Channel not found',
                ]);

                return;
            }

            $channel    = $items[0];
            $snippet    = $channel['snippet'] ?? [];
            $statistics = $channel['statistics'] ?? [];

            $subscribers = (int) ($statistics['subscriberCount'] ?? 0);
            $videos      = (int) ($statistics['videoCount'] ?? 0);
            $views       = (int) ($statistics['viewCount'] ?? 0);

            DB::transaction(function () use (
                $channel,
                $snippet,
                $subscribers,
                $videos,
                $views
            ) {
                $lastSnapshot = $this->profile->latestSnapshot;

                $delta = $lastSnapshot
                    ? $subscribers - $lastSnapshot->subscribers_count
                    : 0;

                ProfileSnapshot::create([
                    'profile_id'        => $this->profile->id,
                    'subscribers_count' => $subscribers,
                    'videos_count'      => $videos,
                    'views_count'       => $views,
                    'subscribers_delta' => $delta,
                    'fetched_at'        => now(),
                ]);

                $this->profile->update([
                    'channel_id'          => $channel['id'],
                    'full_name'           => $snippet['title'] ?? null,
                    'bio'                 => $snippet['description'] ?? null,
                    'profile_picture_url' => $snippet['thumbnails']['high']['url'] ?? null,
                    'profile_url'         => "https://youtube.com/@{$this->profile->username}",
                    'subscribers_count'   => $subscribers,
                    'videos_count'        => $videos,
                    'views_count'         => $views,
                    'status'              => 'fetched',
                    'error_message'       => null,
                    'last_refreshed_at'   => now(),
                ]);
            });

            Redis::del('youtube_consecutive_failures');
            Redis::del('youtube_circuit_open_until');

            $duration = now()->diffInMilliseconds($startTime);

            Log::info('FetchProfileJob completed', [
                'profile_id'  => $this->profile->id,
                'duration_ms' => $duration,
                'outcome'     => 'success',
            ]);

        } catch (Throwable $e) {
            if ($e instanceof \RuntimeException) {
                $failures = Redis::incr(
                    'youtube_consecutive_failures'
                );

                if ($failures >= 10) {
                    Redis::set(
                        'youtube_circuit_open_until',
                        now()->addMinutes(2)->timestamp
                    );

                    Log::error('Circuit breaker opened', [
                        'failures' => $failures,
                    ]);
                }
            }

            $this->profile->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('FetchProfileJob failed', [
                'profile_id' => $this->profile->id,
                'error'      => $e->getMessage(),
            ]);

            throw $e;

        } finally {

            Redis::del($lockKey);
        }
    }
}