<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use App\Http\Controllers\WatchlistController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::post('/webhooks/youtube',
    [WebhookController::class, 'youtube']
);

Route::get('/healthz', function () {
    try {
        DB::connection()->getPdo();
        Redis::ping();

        return response()->json([
            'status' => 'ok',
            'database' => 'ok',
            'redis' => 'ok',
            'timestamp' => now(),
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'status' => 'failed',
            'error' => $e->getMessage(),
        ], 500);
    }
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', 'watchlist')->name('dashboard');

    // Watchlist routes
    Route::get('/watchlist', [WatchlistController::class, 'index'])->name('watchlist.index');
    Route::post('/watchlist', [WatchlistController::class, 'store'])->name('watchlist.store');
    Route::get('/watchlist/{profile}', [WatchlistController::class, 'show'])->name('watchlist.show');
    Route::post('/watchlist/{profile}/refetch', [WatchlistController::class, 'refetch'])->name('watchlist.refetch');
});

require __DIR__.'/settings.php';