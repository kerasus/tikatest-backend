<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnlineExamDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'starts_at',
        'ends_at',
        'time_limit_minutes',
        'visible_at',
        'answers_visible_at',
        'content',
        'solution',
        'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'visible_at' => 'datetime',
        'answers_visible_at' => 'datetime',
        'time_limit_minutes' => 'integer',
        'content' => 'array',
        'solution' => 'array',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(OnlineExamSession::class, 'exam_id');
    }

    public function booklets(): HasMany
    {
        return $this->hasMany(OnlineExamBooklet::class, 'online_exam_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
