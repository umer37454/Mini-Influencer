<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Cast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

#[Fillable([
    'username',
    'channel_id',
    'platform',
    'profile_url',
    'full_name',
    'bio',
    'profile_picture_url',
    'subscribers_count',
    'videos_count',
    'views_count',
    'status',
    'error_message',
    'last_refreshed_at',
])]
class Profile extends Model
{
    protected function casts(): array
    {
        return [
            'subscribers_count' => 'integer',
            'videos_count'      => 'integer',
            'views_count'       => 'integer',
            'last_refreshed_at' => 'datetime',
            'created_at'        => 'datetime',
            'updated_at'        => 'datetime',
        ];
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(ProfileSnapshot::class)->orderBy('fetched_at', 'desc');
    }

    public function latestSnapshot(): HasOne
    {
        return $this->hasOne(ProfileSnapshot::class)->latestOfMany('fetched_at');
    }

    public function scopeStale($query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('last_refreshed_at')
              ->orWhere('last_refreshed_at', '<', now()->subHour());
        })->where('status', '!=', 'fetching');
    }

    public function scopeSearch($query, string $search): Builder
    {
        return $query->where('username', 'ilike', "%{$search}%");
    }
}