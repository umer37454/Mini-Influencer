<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'profile_id',
    'subscribers_count',
    'videos_count',
    'views_count',
    'subscribers_delta',
    'fetched_at',
])]
class ProfileSnapshot extends Model
{
    protected function casts(): array
    {
        return [
            'subscribers_count' => 'integer',
            'videos_count'      => 'integer',
            'views_count'       => 'integer',
            'subscribers_delta' => 'integer',
            'fetched_at'        => 'datetime',
            'created_at'        => 'datetime',
            'updated_at'        => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}