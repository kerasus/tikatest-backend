<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property int $homework_id
 * @property array<array-key, mixed>|null $content
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Homework|null $homework
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkAttachment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkAttachment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkAttachment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkAttachment whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkAttachment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkAttachment whereHomeworkId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkAttachment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkAttachment whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HomeworkAttachment whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class HomeworkAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'homework_id',
        'content',
        'sort_order',
    ];

    protected $casts = [
        'content' => 'array',
    ];

    public function homework(): BelongsTo
    {
        return $this->belongsTo(Homework::class);
    }


    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * اکسسور خودکار برای تبدیل مسیر فایل ضمیمه تکلیف به URL کامل
     */
    protected function content(): Attribute
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
