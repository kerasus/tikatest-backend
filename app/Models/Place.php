<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Place extends Model
{
    protected $fillable = [
        'provider',
        'external_id',
        'name',
        'address',
        'phone',
        'lat',
        'lng',
        'url',
        'keyword',
        'raw_data',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'raw_data' => 'array',
    ];

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function scopeProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    public function scopeTagged(Builder $query): Builder
    {
        return $query->whereHas('tags');
    }

    public function scopeUntagged(Builder $query): Builder
    {
        return $query->whereDoesntHave('tags');
    }
}
