<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use App\Http\Controllers\WatchlistController;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', 'watchlist')->name('dashboard');

    // Watchlist routes
    Route::get('/watchlist', [WatchlistController::class, 'index'])->name('watchlist.index');
    Route::post('/watchlist', [WatchlistController::class, 'store'])->name('watchlist.store');
    Route::get('/watchlist/{profile}', [WatchlistController::class, 'show'])->name('watchlist.show');
    Route::post('/watchlist/{profile}/refetch', [WatchlistController::class, 'refetch'])->name('watchlist.refetch');
});

require __DIR__.'/settings.php';