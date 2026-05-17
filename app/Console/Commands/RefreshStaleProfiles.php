<?php

namespace App\Console\Commands;

use App\Jobs\FetchProfileJob;
use App\Models\Profile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RefreshStaleProfiles extends Command
{
    protected $signature   = 'profiles:refresh';
    protected $description = 'Refresh all stale profiles older than 1 hour';

    public function handle(): void
    {
        $lock = Cache::lock('profiles:refresh:lock', 600);

        if (!$lock->get()) {
            $this->info('Previous refresh still running — skipping');
            return;
        }

        try {
            $profiles = Profile::stale()->get();

            if ($profiles->isEmpty()) {
                $this->info('No stale profiles found');
                
                return;
            }

            $this->info("Found {$profiles->count()} stale profiles — dispatching jobs");

            $profiles->each(function (Profile $profile) {
                FetchProfileJob::dispatch($profile);
                $this->info("Dispatched job for: {$profile->username}");
            });
        } finally {
            $lock->release();
        }
    }
}