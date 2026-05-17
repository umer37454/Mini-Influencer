<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Database\Eloquent\Builder;
use App\Jobs\FetchProfileJob;

class WatchlistController extends Controller
{
    public function index(Request $request): Response
    {
        $profiles = Profile::query()
            ->with('latestSnapshot')
            ->when($request->search, fn(Builder $q) => $q->search($request->search))
            ->when($request->status, fn(Builder $q) => $q->where('status', $request->status))
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('watchlist/index', [
            'profiles' => $profiles,
            'filters'  => $request->only(['search', 'status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'username' => [
                'required',
                'string',
                'max:100',
                'regex:/^@?[\w.]+$/',
            ],
        ]);

        $username = strtolower(trim(ltrim($request->username, '@')));

        Profile::updateOrCreate(
            ['username' => $username, 'platform' => 'youtube'],
            ['status'   => 'pending', 'error_message' => null]
        );

        return redirect()->route('watchlist.index')
            ->with('success', 'Channel added successfully!');
    }

    public function show(Profile $profile): Response
    {
        $profile->load(['snapshots']);

        return Inertia::render('watchlist/show', [
            'profile'   => $profile,
            'snapshots' => $profile->snapshots()
                ->orderBy('fetched_at', 'desc')
                ->paginate(10)
                ->withQueryString(),
        ]);
    }

    public function refetch(Profile $profile)
    {
        $profile->update([
            'status' => 'pending',
        ]);

        FetchProfileJob::dispatch($profile);

        return back();
    }
}