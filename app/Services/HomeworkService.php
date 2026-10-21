<?php

namespace App\Services;

use App\Models\User;
use App\Models\Homework;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\AcademicTerm;
use App\Models\TermEnrollment;
use Illuminate\Support\Carbon;
use Illuminate\Http\UploadedFile;
use App\Models\HomeworkAttachment;
use App\Models\HomeworkSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;

class HomeworkService
{
    public function create(Request $request): Homework
    {
        return DB::transaction(function () use ($request) {
            $homework = Homework::create($this->normalizeDueDate($request->only([
                'title', 'lesson_id', 'term_id', 'description', 'due_date',
            ])) + ['created_by' => $request->user()->id]);

            $this->syncAttachments($homework, $request);
            $this->syncHomeworkRelations($homework, $request);

            return $this->loadHomework($homework);
        });
    }

    public function update(Request $request, Homework $homework): Homework
    {
        return DB::transaction(function () use ($request, $homework) {
            $homework->update($this->normalizeDueDate($request->only([
                'title', 'lesson_id', 'term_id', 'description', 'due_date',
            ])));

            if ($request->has('attachments') || $request->has('content') || $request->hasFile('attachments')) {
                $this->syncAttachments($homework, $request);
            }

            $this->syncHomeworkRelations($homework, $request);

            return $this->loadHomework($homework);
        });
    }

    private function loadHomework(Homework $homework): Homework
    {
        return Homework::with(['attachments', 'academicLevels', 'classes', 'lesson', 'term'])
            ->findOrFail($homework->id);
    }

    public function studentSchoolIds(User $user): array
    {
        return $user->termEnrollments()
            ->whereNotNull('school_id')
            ->pluck('school_id')
            ->unique()
            ->toArray();
    }

    public function studentHomeworksQuery(User $student, array $filters = []): Builder
    {
        $schoolIds = isset($filters['school_id'])
            ? [(int) $filters['school_id']]
            : $this->studentSchoolIds($student);

        $termEnrollments = $student->termEnrollments()
            ->whereIn('school_id', $schoolIds)
            ->whereNotNull('class_id')
            ->with('schoolClass:id,academic_level_id')
            ->get(['id', 'class_id']);

        $query = Homework::query()
            ->whereHas('term', fn (Builder $query) => $query->whereIn('school_id', $schoolIds))
            ->forStudentTargets([
                'class_ids' => $termEnrollments->pluck('class_id')->filter()->unique()->values()->all(),
                'academic_level_ids' => $termEnrollments->pluck('schoolClass.academic_level_id')->filter()->unique()->values()->all(),
            ])
            ->with([
                'lesson',
                'submission' => function (HasOne $query) use ($student) {
                    $query->where('student_id', $student->id);
                },
            ]);

        if (! empty($filters['title'])) {
            $query->where('title', 'like', '%'.$filters['title'].'%');
        }
        if (isset($filters['lesson_id'])) {
            $query->where('lesson_id', $filters['lesson_id']);
        }
        if (isset($filters['term_id'])) {
            $query->where('term_id', $filters['term_id']);
        }
        if (! empty($filters['id_in'])) {
            $query->whereIn('id', $filters['id_in']);
        }
        $from = $filters['from'] ?? $filters['due_date_since_date'] ?? null;
        $to = $filters['to'] ?? $filters['due_date_till_date'] ?? null;

        $this->filterDueDate($query, $from, $to, ! empty($filters['include_undated']));

        $status = $filters['status'] ?? null;
        if ($status === 'submitted') {
            $query->whereHas('submissions', fn (Builder $query) => $query
                ->where('student_id', $student->id)->whereNotNull('submitted_at'));
        } elseif ($status === 'pending' || $status === 'overdue') {
            $query->whereDoesntHave('submissions', fn (Builder $query) => $query
                ->where('student_id', $student->id)->whereNotNull('submitted_at'));
            if ($status === 'overdue') {
                $query->where('due_date', '<', now());
            }
        }

        return $query;
    }

    public function filterDueDate(Builder $query, ?string $from, ?string $to, bool $includeUndated = false): void
    {
        if ($from) {
            $fromDate = Carbon::parse($from);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
                $fromDate->startOfDay();
            }

            $query->where(function (Builder $dueDateQuery) use ($fromDate, $includeUndated) {
                $dueDateQuery->where('due_date', '>=', $fromDate);
                if ($includeUndated) {
                    $dueDateQuery->orWhereNull('due_date');
                }
            });
        }

        if ($to) {
            $toDate = Carbon::parse($to);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
                $toDate->endOfDay();
            }

            $query->where('due_date', '<=', $toDate);
        }
    }

    private function normalizeDueDate(array $attributes): array
    {
        if (isset($attributes['due_date']) && is_string($attributes['due_date'])
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $attributes['due_date'])) {
            $attributes['due_date'] = Carbon::parse($attributes['due_date'])->endOfDay();
        }

        return $attributes;
    }

    public function viewForStudent(int $homeworkId, int $studentId): array
    {
        $homework = Homework::with(['lesson', 'attachments'])->findOrFail($homeworkId);
        $this->assertEnrolled($homework, $studentId);

        $submission = HomeworkSubmission::where('homework_id', $homeworkId)
            ->where('student_id', $studentId)
            ->first();

        if (! $submission) {
            $submission = HomeworkSubmission::create([
                'homework_id' => $homeworkId,
                'student_id' => $studentId,
                'student_seen_at' => now(),
            ]);
        } elseif (! $submission->student_seen_at) {
            $submission->update(['student_seen_at' => now()]);
        }

        return ['homework' => $homework, 'submission' => $submission];
    }

    public function submit(Request $request, int $homeworkId): ?HomeworkSubmission
    {
        $studentId = $request->user()->id;
        $homework = Homework::findOrFail($homeworkId);
        $this->assertEnrolled($homework, $studentId);

        $submittedAt = now();
        if ($homework->due_date && $homework->due_date->lt($submittedAt)) {
            return null;
        }

        $existingSubmission = HomeworkSubmission::where('homework_id', $homeworkId)
            ->where('student_id', $studentId)
            ->first();
        $oldFilePath = $existingSubmission
            ? $this->extractStoredPath($existingSubmission->getRawOriginal('content'))
            : null;

        $contentInput = $request->input('content');
        $normalizedContent = is_array($contentInput) ? $contentInput : (is_string($contentInput) ? json_decode($contentInput, true) : []);
        $normalizedContent = $normalizedContent ?: [];

        if ($request->hasFile('submission_file')) {
            $file = $request->file('submission_file');
            $normalizedContent = [
                'type' => $file->getClientMimeType() === 'application/pdf' ? 'pdf' : 'image',
                'path' => $this->storeHomeworkSubmissionFile($file, $request, $homework),
            ];
        }

        $submission = HomeworkSubmission::updateOrCreate(
            ['homework_id' => $homeworkId, 'student_id' => $studentId],
            [
                'content' => $normalizedContent ?: null,
                'submitted_at' => $submittedAt,
                'student_seen_at' => $submittedAt,
                'operator_seen_at' => null,
            ]
        );

        if ($request->hasFile('submission_file') && $oldFilePath && Storage::disk('public')->exists($oldFilePath)) {
            Storage::disk('public')->delete($oldFilePath);
        }

        return $submission;
    }

    private function assertEnrolled(Homework $homework, int $studentId): void
    {
        if ($homework->class_id && ! TermEnrollment::where('user_id', $studentId)
            ->where('class_id', $homework->class_id)->exists()) {
            abort(403, 'You are not enrolled in this class');
        }
    }

    public function deleteAttachments(Homework $homework): void
    {
        foreach ($homework->attachments as $attachment) {
            $filePath = $this->extractStoredPath($attachment->getRawOriginal('content'));
            if ($filePath && Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);
            }
        }
    }

    public function createAttachment(Request $request, Homework $homework): HomeworkAttachment
    {
        return DB::transaction(function () use ($request, $homework) {
            return HomeworkAttachment::create([
                'homework_id' => $homework->id,
                'content' => $this->processAttachmentContent($request, $homework),
                'sort_order' => $request->input('sort_order', 0),
                'created_by' => $request->user()->id,
            ]);
        });
    }

    public function deleteAttachment(int $homeworkId, int $attachmentId): void
    {
        $attachment = HomeworkAttachment::query()
            ->where('homework_id', $homeworkId)
            ->where('id', $attachmentId)
            ->firstOrFail();
        $filePath = $this->extractStoredPath($attachment->getRawOriginal('content'));
        $attachment->delete();

        if ($filePath && Storage::disk('public')->exists($filePath)) {
            Storage::disk('public')->delete($filePath);
        }
    }

    private function syncAttachments(Homework $homework, Request $request): void
    {
        $existingAttachments = $homework->attachments()->get();
        $oldFilePaths = [];

        foreach ($existingAttachments as $attachment) {
            $path = $this->extractStoredPath($attachment->getRawOriginal('content'));
            if ($path) {
                $oldFilePaths[] = $path;
            }
            $attachment->delete();
        }

        $retainedPaths = [];
        $attachments = $request->input('attachments', []);
        if (is_string($attachments)) {
            $decoded = json_decode($attachments, true);
            $attachments = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($attachments)) {
            $attachments = [];
        }

        foreach ($attachments as $index => $attachmentData) {
            if (! is_array($attachmentData)) {
                $attachmentData = [];
            }
            $fileKey = "attachments.{$index}.file";
            $content = $attachmentData;

            if ($request->hasFile($fileKey)) {
                $file = $request->file($fileKey);
                $content['path'] = $this->storeHomeworkAttachmentFile($file, $request, $homework);
                if (! isset($content['type'])) {
                    $content['type'] = $file->getClientMimeType() === 'application/pdf' ? 'pdf' : 'image';
                }
            } else {
                $storedPath = $this->extractStoredPath($content);
                if ($storedPath) {
                    $content['path'] = $storedPath;
                    $retainedPaths[] = $storedPath;
                }
            }

            $homework->attachments()->create([
                'content' => $content,
                'sort_order' => $attachmentData['sort_order'] ?? $index,
                'created_by' => $request->user()->id,
            ]);
        }

        $directContent = $request->input('content');
        if (is_string($directContent)) {
            $directContent = json_decode($directContent, true);
        }
        if (is_array($directContent) && ! empty($directContent)) {
            $directPath = $this->extractStoredPath($directContent);
            if ($directPath) {
                $retainedPaths[] = $directPath;
            }
            $homework->attachments()->create([
                'content' => $directContent,
                'sort_order' => 0,
                'created_by' => $request->user()->id,
            ]);
        }

        $pathsToDelete = array_diff(array_unique($oldFilePaths), array_filter(array_unique($retainedPaths)));
        foreach ($pathsToDelete as $path) {
            if ($path && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    private function syncHomeworkRelations(Homework $homework, Request $request): void
    {
        if ($request->has('academic_level_ids')) {
            $homework->academicLevels()->sync($request->input('academic_level_ids', []));
        }
        if ($request->has('class_ids')) {
            $homework->classes()->sync($request->input('class_ids', []));
        }
    }

    private function processAttachmentContent(Request $request, Homework $homework): ?array
    {
        $content = $request->input('content');
        if (is_string($content)) {
            $decoded = json_decode($content, true);
            $content = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($content)) {
            $content = [];
        }
        $file = $request->file('attachment_file');
        if ($file instanceof UploadedFile) {
            $content['path'] = $this->storeHomeworkAttachmentFile($file, $request, $homework);
            if (! isset($content['type'])) {
                $content['type'] = $file->getClientMimeType() === 'application/pdf' ? 'pdf' : 'image';
            }
        }

        return ! empty($content) ? $content : null;
    }

    private function storeHomeworkAttachmentFile(UploadedFile $file, Request $request, Homework $homework, string $prefix = 'homework'): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?: 'bin'));
        $filename = sprintf('%s_%s.%s', $prefix, Str::random(24), $extension);

        return $file->storeAs('homework-attachments/' . $this->resolveSchoolSlug($request, $homework), $filename, 'public');
    }

    private function storeHomeworkSubmissionFile(UploadedFile $file, Request $request, Homework $homework): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?: 'bin'));
        $filename = sprintf('homework_submission_%s.%s', Str::random(24), $extension);

        return $file->storeAs('homework-submissions/' . $this->resolveSchoolSlug($request, $homework), $filename, 'public');
    }

    private function resolveSchoolSlug(Request $request, ?Homework $homework = null): string
    {
        $termId = $request->input('term_id') ?: $homework?->term_id;
        $schoolId = $termId ? AcademicTerm::query()->whereKey($termId)->value('school_id') : null;
        if (! $schoolId) {
            $schoolId = $request->user()?->school_id;
        }
        if (! $schoolId && $request->user()) {
            $schoolId = $request->user()->termEnrollments()->whereNotNull('school_id')->value('school_id');
        }
        if (! $schoolId) {
            return 'default-school';
        }
        $schoolSlug = \App\Models\School::query()->whereKey($schoolId)->value('slug');

        return $schoolSlug ? (Str::slug($schoolSlug) ?: 'school-' . $schoolId) : 'school-' . $schoolId;
    }

    private function extractStoredPath(mixed $content): ?string
    {
        if (is_string($content)) {
            $decoded = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $content = $decoded;
            }
        }
        $path = is_array($content) ? ($content['path'] ?? null) : (is_string($content) ? $content : null);
        if (! is_string($path) || trim($path) === '') {
            return null;
        }
        if (Str::startsWith($path, ['http://', 'https://'])) {
            $urlPath = parse_url($path, PHP_URL_PATH);
            $diskUrlPath = rtrim((string) parse_url(Storage::disk('public')->url(''), PHP_URL_PATH), '/');
            if ($urlPath && $diskUrlPath && Str::startsWith($urlPath, $diskUrlPath . '/')) {
                return ltrim(Str::after($urlPath, $diskUrlPath . '/'), '/');
            }

            return null;
        }

        return ltrim($path, '/');
    }

    /**
     * محاسبه وضعیت تکلیف دانش‌آموز بر اساس مهلت سرور و وضعیت ارسال
     */
    public function resolveStudentStatus(Homework $homework, ?HomeworkSubmission $submission): array
    {
        $hasSubmitted = ! empty($submission?->submitted_at);
        $hasFeedback = ! empty($submission?->feedback);

        // مهلت تکلیف بر اساس تایم سرور
        $isPastDue = false;
        if ($homework->due_date) {
            $dueDate = $homework->due_date instanceof Carbon
                ? $homework->due_date
                : Carbon::parse($homework->due_date);

            $isPastDue = now()->greaterThan($dueDate);
        }

        // حالت ۱: ارسال شده و بازخورد معلم ثبت شده
        if ($hasSubmitted && $hasFeedback) {
            $state = 'reviewed';
        }
        // حالت ۲: ارسال شده ولی هنوز بررسی نشده
        elseif ($hasSubmitted) {
            $state = 'submitted';
        }
        // حالت ۳: ارسال نشده و مهلت سرور به پایان رسیده
        elseif ($isPastDue) {
            $state = 'overdue';
        }
        // حالت ۴: ارسال نشده و هنوز مهلت دارد
        else {
            $state = 'pending';
        }

        return [
            'state' => $state,
            'is_submitted' => $hasSubmitted,
            'is_reviewed' => $hasFeedback,
            'is_past_due' => $isPastDue,
        ];
    }

    /**
     * دریافت لیست دانش‌آموزان واجد شرایط یک تکلیف به تفکیک پایه و کلاس
     * به همراه وضعیت مشاهده/ارسال/بررسی (HomeworkSubmission) برای هر دانش‌آموز
     *
     * state: reviewed | submitted | seen | not_seen
     * flags: has_seen, has_submitted, is_reviewed
     *
     * نکته: طبق خواسته شما term_id برای enrollments فیلتر می‌شود.
     */
    public function getHomeworkSubmissionsGroupedByClass(Homework $homework): array
    {
        // 1) کلاس‌های صریح تکلیف (homework_classes)
        $explicitClasses = \App\Models\SchoolClass::query()
            ->whereIn('id', DB::table('homework_classes')->where('homework_id', $homework->id)->pluck('class_id'))
            ->with('academicLevel')
            ->get();

        $explicitClassIds = $explicitClasses->pluck('id')->all();
        $explicitByLevelId = $explicitClasses->groupBy('academic_level_id'); // [level_id => Collection<SchoolClass>]

        // 2) پایه‌های تکلیف (homework_academic_levels)
        $levelIds = $homework->academicLevels()
            ->pluck('academic_levels.id')
            ->all();

        $levelsWithClasses = collect();
        if (! empty($levelIds)) {
            $levelsWithClasses = \App\Models\AcademicLevel::query()
                ->whereIn('id', $levelIds)
                ->with(['classes.academicLevel'])
                ->get();
        }

        // 3) ساخت لیست نهایی کلاس‌های مشمول تکلیف طبق قاعده ترکیبی:
        // - اگر برای یک پایه کلاس صریح داریم => فقط همان‌ها
        // - وگرنه => همه کلاس‌های آن پایه
        $eligibleClasses = collect();

        foreach ($levelsWithClasses as $level) {
            $levelId = $level->id;

            if ($explicitByLevelId->has($levelId) && $explicitByLevelId->get($levelId)->isNotEmpty()) {
                $eligibleClasses = $eligibleClasses->merge($explicitByLevelId->get($levelId));
            } else {
                $eligibleClasses = $eligibleClasses->merge($level->classes);
            }
        }

        // اگر پایه‌ای ست نبود ولی کلاس صریح بود => همان‌ها
        if (empty($levelIds) && ! empty($explicitClassIds)) {
            $eligibleClasses = $explicitClasses;
        }

        // یکتا
        $eligibleClasses = $eligibleClasses->unique('id')->values();

        // 4) سابمیشن‌ها: برای هر دانش‌آموز یک رکورد (در مدل شما یکتاست)
        $submissionsByStudent = HomeworkSubmission::query()
            ->where('homework_id', $homework->id)
            ->get()
            ->keyBy('student_id');

        // 5) ساخت خروجی: پایه -> کلاس -> دانش‌آموزان
        $classesGroupedByLevel = $eligibleClasses->groupBy('academic_level_id');

        $levelsPayload = [];

        foreach ($classesGroupedByLevel as $levelId => $classesInLevel) {
            $levelName = $classesInLevel->first()?->academicLevel?->name ?? 'نامشخص';

            $classesPayload = [];

            foreach ($classesInLevel as $schoolClass) {
                $enrollmentsQuery = TermEnrollment::query()
                    ->where('class_id', $schoolClass->id)
                    ->active()
                    ->with('user');

                // طبق خواسته شما: فیلتر ترم اجباری
                if ($homework->term_id) {
                    $enrollmentsQuery->where('term_id', $homework->term_id);
                }

                $enrollments = $enrollmentsQuery->get();

                $studentsPayload = [];

                foreach ($enrollments as $enrollment) {
                    $student = $enrollment->user;
                    if (! $student) {
                        continue;
                    }

                    $submission = $submissionsByStudent->get($student->id);

                    $hasSeen = $submission !== null && ! empty($submission->student_seen_at);
                    $hasSubmitted = $submission !== null && ! empty($submission->submitted_at);
                    $isReviewed = $submission !== null && ! empty($submission->feedback);

                    $state = match (true) {
                        $isReviewed => 'reviewed',
                        $hasSubmitted => 'submitted',
                        $hasSeen => 'seen',
                        default => 'not_seen',
                    };

                    $studentsPayload[] = [
                        'student' => [
                            'id' => $student->id,
                            'name' => $student->name ?? trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
                            'national_code' => $student->national_code ?? null,
                            'mobile' => $student->mobile ?? null,
                        ],
                        'enrollment_id' => $enrollment->id,

                        'has_seen' => $hasSeen,
                        'has_submitted' => $hasSubmitted,
                        'is_reviewed' => $isReviewed,
                        'state' => $state,

                        'submission' => $submission ? [
                            'id' => $submission->id,
                            'homework_id' => $submission->homework_id,
                            'student_id' => $submission->student_id,
                            'student_seen_at' => $submission->student_seen_at,
                            'submitted_at' => $submission->submitted_at,
                            'operator_seen_at' => $submission->operator_seen_at,
                            'feedback' => $submission->feedback,
                            'content' => $submission->content, // accessor شما normalize می‌کنه
                            'created_at' => $submission->created_at,
                            'updated_at' => $submission->updated_at,
                        ] : null,
                    ];
                }

                $classesPayload[] = [
                    'class_id' => $schoolClass->id,
                    'class_name' => $schoolClass->name,
                    'total_students' => count($studentsPayload),
                    'reviewed_count' => collect($studentsPayload)->where('state', 'reviewed')->count(),
                    'submitted_count' => collect($studentsPayload)->where('state', 'submitted')->count(),
                    'seen_count' => collect($studentsPayload)->where('state', 'seen')->count(),
                    'not_seen_count' => collect($studentsPayload)->where('state', 'not_seen')->count(),
                    'students' => $studentsPayload,
                ];
            }

            $levelsPayload[] = [
                'academic_level_id' => (int) $levelId,
                'academic_level_name' => $levelName,
                'classes' => $classesPayload,
            ];
        }

        return [
            'homework_id' => $homework->id,
            'homework_title' => $homework->title ?? null,
            'term_id' => $homework->term_id,
            'levels' => $levelsPayload,
        ];
    }

}
