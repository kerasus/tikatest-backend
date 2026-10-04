<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolSkyroomAccount extends Model
{
    protected $fillable = [
        'school_id',
        'title',
        'username',
        'api_key',
        'is_active',
    ];

    protected $hidden = [
        'api_key',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(SkyroomRoom::class, 'skyroom_account_id');
    }
}
