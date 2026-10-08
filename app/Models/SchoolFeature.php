<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolFeature extends Model
{
    public const SKYROOM = 'skyroom';

    public const FTP_PANEL = 'ftp_panel';

    public const KEYS = [
        self::SKYROOM,
        self::FTP_PANEL,
    ];

    protected $fillable = [
        'school_id',
        'feature_key',
        'is_enabled',
        'settings',
        'expires_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'settings' => 'array',
        'expires_at' => 'datetime',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
