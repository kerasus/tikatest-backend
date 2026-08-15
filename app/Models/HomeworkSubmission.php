<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeworkSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'homework_id',
        'student_id',
        'submission_text',
        'submission_file',
        'submitted_at',
        'student_seen_at',
        'operator_seen_at',
        'grade',
        'feedback',
        'graded_by',
        'graded_at',
        'content',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'student_seen_at' => 'datetime',
        'operator_seen_at' => 'datetime',
        'graded_at' => 'datetime',
        'grade' => 'decimal:2',
        'content' => 'array',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function homework(): BelongsTo
    {
        return $this->belongsTo(Homework::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}
