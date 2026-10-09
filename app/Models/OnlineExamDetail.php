<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property int $exam_id
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 * @property int|null $time_limit_minutes
 * @property \Illuminate\Support\Carbon|null $visible_at
 * @property \Illuminate\Support\Carbon|null $answers_visible_at
 * @property array<array-key, mixed>|null $content
 * @property array<array-key, mixed>|null $solution
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OnlineExamBooklet> $booklets
 * @property-read int|null $booklets_count
 * @property-read \App\Models\User|null $createdBy
 * @property-read \App\Models\Exam $exam
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OnlineExamSession> $sessions
 * @property-read int|null $sessions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamDetail newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamDetail newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamDetail query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamDetail whereAnswersVisibleAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamDetail whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamDetail whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamDetail whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamDetail whereEndsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamDetail whereExamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamDetail whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamDetail whereSolution($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamDetail whereStartsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamDetail whereTimeLimitMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamDetail whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OnlineExamDetail whereVisibleAt($value)
 * @mixin \Eloquent
 */
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

    public function answerKeys(): HasMany
    {
        // foreignKey: exam_id روی جدول online_exam_answer_keys
        // localKey: exam_id روی جدول online_exam_details
        return $this->hasMany(OnlineExamAnswerKey::class, 'exam_id', 'exam_id')
            ->orderBy('question_number');
    }

    /**
     * اکسسور خودکار برای تبدیل مسیر فایل سوالات به URL کامل
     */
    protected function content(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->normalizeFilePayload($value),
        );
    }

    /**
     * اکسسور خودکار برای تبدیل مسیر فایل پاسخنامه به URL کامل
     */
    protected function solution(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->normalizeFilePayload($value),
        );
    }

    /**
     * هلپر عمومی برای تبدیل فیلدهای ساختاریافته فایل
     */
    private function normalizeFilePayload($value): mixed
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (! is_array($value) || empty($value['path'])) {
            return $value;
        }

        $path = $value['path'];

        // اگر از قبل URL کامل است دست نزن
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $value;
        }

        // تبدیل مسیر محلی استوریج به URL کامل
        // اگر APP_URL روی matiaan.ir/api تنظیم شده، خروجی دقیقاً https://matiaan.ir/api/storage/... خواهد شد
        $value['path'] = Storage::disk('public')->url($path);

        return $value;
    }

}
