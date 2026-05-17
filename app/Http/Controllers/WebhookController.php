<?php

namespace App\Http\Controllers;

use App\Jobs\FetchProfileJob;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class WebhookController extends Controller
{
    public function youtube(Request $request)
    {
        $signature = $request->header('X-Signature');

        $expectedSignature = hash_hmac(
            'sha256',
            $request->getContent(),
            config('services.youtube_webhook.secret')
        );

        if (! hash_equals($expectedSignature, $signature)) {

            Log::warning('Invalid webhook signature');

            return response()->json([
                'message' => 'Invalid signature',
            ], 401);
        }

        $eventId = $request->header('X-Event-Id');

        if (Redis::get("webhook_event:{$eventId}")) {

            Log::warning('Duplicate webhook event', [
                'event_id' => $eventId,
            ]);

            return response()->json([
                'message' => 'Duplicate event',
            ], 409);
        }

        Redis::set(
            "webhook_event:{$eventId}",
            1,
            'EX',
            300
        );

        $channelId = $request->input('channel_id');

        $profile = Profile::query()
            ->where('channel_id', $channelId)
            ->first();

        if (!$profile) {
            return response()->json([
                'message' => 'Profile not found',
            ], 404);
        }

        FetchProfileJob::dispatch($profile);

        Log::info('Webhook processed successfully', [
            'profile_id' => $profile->id,
            'event_id' => $eventId,
        ]);

        return response()->json([
            'received' => true,
        ]);
    }
}