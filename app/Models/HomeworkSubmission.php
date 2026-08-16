<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeworkSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'homework_id',
        'student_id',
        'submitted_at',
        'student_seen_at',
        'operator_seen_at',
        'feedback',
        'content',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'student_seen_at' => 'datetime',
        'operator_seen_at' => 'datetime',
        'content' => 'array',
    ];

    public function homework(): BelongsTo
    {
        return $this->belongsTo(Homework::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
