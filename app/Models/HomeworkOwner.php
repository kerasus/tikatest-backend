<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeworkOwner extends Model
{
    use HasFactory;

    protected $fillable = [
        'homework_id',
        'user_id',
        'read_status',
        'read_at',
        'submission_file',
        'submitted_at',
    ];

    protected $casts = [
        'read_status' => 'boolean',
        'read_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function homework(): BelongsTo
    {
        return $this->belongsTo(Homework::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
