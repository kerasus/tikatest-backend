<?php

namespace App\Services;

use App\Models\Exam;
use Illuminate\Http\Request;
use App\Models\AcademicTerm;
use App\Models\TermEnrollment;
use Illuminate\Support\Carbon;
use App\Models\OnlineExamDetail;
use Illuminate\Http\UploadedFile;
use App\Models\OnlineExamBooklet;
use App\Models\InPersonExamResult;
use App\Models\InPersonExamDetail;
use App\Models\OnlineExamAnswerKey;
use App\Models\ExamCategoryTermLimit;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class ExamService
{
    public function createOnlineExam(array $validated, Request $request): Exam
    {
        $termId = $validated['term_id'] ?? null;
        $this->enforceOnlineLessonExclusivity($validated);
        $exam = Exam::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'lesson_id' => $validated['lesson_id'],
            'min_passing_score' => $validated['min_passing_score'] ?? null,
            'max_score' => $validated['max_score'] ?? null,
            'delivery_mode' => 'online',
            'exam_category_id' => $validated['exam_category_id'],
            'term_id' => $termId,
            'created_by' => $validated['created_by'] ?? $request->user()->id,
        ]);

        $exam->occurrence = $validated['occurrence'] ?? $this->enforceTermOccurrence(
            $exam->id,
            $validated['exam_category_id'],
            $termId
        );
        $exam->save();

        $onlineDetail = OnlineExamDetail::create([
            'exam_id' => $exam->id,
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
            'time_limit_minutes' => $validated['time_limit_minutes'] ?? null,
            'visible_at' => $validated['visible_at'] ?? null,
            'answers_visible_at' => $validated['answers_visible_at'] ?? null,
            'content' => $this->processExamContent($request, 'content'),
            'solution' => $this->processExamContent($request, 'solution'),
            'created_by' => $request->user()->id,
        ]);

        if (! empty($validated['booklets'])) {
            $this->createBooklets($onlineDetail, $validated['booklets'], ! empty($validated['lesson_id']));
        }

        if (! empty($validated['answer_keys'])) {
            $this->createAnswerKeys($exam, $validated['answer_keys']);
        }

        if (! empty($validated['class_ids'])) {
            $exam->classes()->sync($validated['class_ids'], false);
        }

        if (! empty($validated['academic_level_ids'])) {
            $exam->academicLevels()->sync($validated['academic_level_ids'], false);
        }

        return $exam;
    }

    public function updateOnlineExam(Exam $exam, array $validated, Request $request): Exam
    {
        $this->enforceOnlineLessonExclusivity($validated);
        $exam->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'lesson_id' => $validated['lesson_id'] ?? null,
            'min_passing_score' => $validated['min_passing_score'] ?? null,
            'max_score' => $validated['max_score'] ?? null,
            'delivery_mode' => 'online',
            'exam_category_id' => $validated['exam_category_id'],
            'created_by' => $validated['created_by'] ?? $request->user()->id,
        ]);

        $exam->term_id = $validated['term_id'] ?? $exam->term_id;
        if (array_key_exists('occurrence', $validated) && $validated['occurrence'] !== null) {
            $exam->occurrence = $validated['occurrence'];
        } elseif ($validated['term_id'] ?? null) {
            $exam->occurrence = $this->enforceTermOccurrence(
                $exam->id,
                $exam->exam_category_id,
                $validated['term_id']
            );
        }
        $exam->save();

        $existingDetail = OnlineExamDetail::where('exam_id', $exam->id)->first();
        $updateData = [
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
            'time_limit_minutes' => $validated['time_limit_minutes'] ?? null,
            'visible_at' => $validated['visible_at'] ?? null,
            'answers_visible_at' => $validated['answers_visible_at'] ?? null,
            'created_by' => $request->user()->id,
        ];

        // نگه‌داشتن مسیرهای قدیمی برای پاک‌سازی در انتهای متد
        $oldContentPath = $existingDetail?->content['path'] ?? null;
        $oldSolutionPath = $existingDetail?->solution['path'] ?? null;
        $mustDeleteOldContentFile = false;
        $mustDeleteOldSolutionFile = false;

        // پردازش Content
        if ($request->has('content') || $request->hasFile('content_file')) {
            $content = $this->processExamContent($request, 'content');
            $content = is_array($content) ? $content : [];

            // اگر فایل جدیدی نیامده ولی مسیر قبلی وجود داشته، مسیر را حفظ می‌کنیم
            if (! $request->hasFile('content_file') && $oldContentPath && ! isset($content['path']) && ! empty($content)) {
                $content['path'] = $oldContentPath;
            }

            $updateData['content'] = ! empty($content) ? $content : null;

            // شرط حذف: یا فایل جدید آپلود شده، یا کلاً content خالی/حذف شده در حالی که فایل قبلی وجود داشت
            if ($oldContentPath && ($request->hasFile('content_file') || empty($content['path']))) {
                $mustDeleteOldContentFile = true;
            }
        }

        // پردازش Solution
        if ($request->has('solution') || $request->hasFile('solution_file')) {
            $solution = $this->processExamContent($request, 'solution');
            $solution = is_array($solution) ? $solution : [];

            // اگر فایل جدیدی نیامده ولی مسیر قبلی وجود داشته، مسیر را حفظ می‌کنیم
            if (! $request->hasFile('solution_file') && $oldSolutionPath && ! isset($solution['path']) && ! empty($solution)) {
                $solution['path'] = $oldSolutionPath;
            }

            $updateData['solution'] = ! empty($solution) ? $solution : null;

            // شرط حذف: یا فایل جدید آپلود شده، یا کلاً solution خالی/حذف شده در حالی که فایل قبلی وجود داشت
            if ($oldSolutionPath && ($request->hasFile('solution_file') || empty($solution['path']))) {
                $mustDeleteOldSolutionFile = true;
            }
        }

        $onlineDetail = OnlineExamDetail::updateOrCreate(
            ['exam_id' => $exam->id],
            $updateData
        );

        if (isset($validated['booklets'])) {
            $onlineDetail->booklets()->delete();
            $this->createBooklets($onlineDetail, $validated['booklets'], ! empty($validated['lesson_id']));
        }

        if (isset($validated['answer_keys'])) {
            OnlineExamAnswerKey::where('exam_id', $exam->id)->delete();
            $this->createAnswerKeys($exam, $validated['answer_keys']);
        }

        if (! empty($validated['class_ids'])) {
            $exam->classes()->sync($validated['class_ids']);
        }

        if (! empty($validated['academic_level_ids'])) {
            $exam->academicLevels()->sync($validated['academic_level_ids']);
        }

        // 🌟 مرحله نهایی: پاک کردن فایل‌های قدیمی از Storage بعد از اتمام همه عملیات‌های DB
        if ($mustDeleteOldContentFile && $oldContentPath) {
            Storage::disk('public')->delete($oldContentPath);
        }

        if ($mustDeleteOldSolutionFile && $oldSolutionPath) {
            Storage::disk('public')->delete($oldSolutionPath);
        }

        return $exam;
    }

    public function createInPersonExam(array $validated, Request $request): Exam
    {
        $termId = $validated['term_id'] ?? null;
        $exam = Exam::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'lesson_id' => $validated['lesson_id'],
            'min_passing_score' => $validated['min_passing_score'] ?? null,
            'max_score' => $validated['max_score'] ?? null,
            'delivery_mode' => 'in_person',
            'exam_category_id' => $validated['exam_category_id'],
            'term_id' => $termId,
            'created_by' => $validated['created_by'] ?? $request->user()->id,
        ]);

        $exam->occurrence = $validated['occurrence'] ?? $this->enforceTermOccurrence(
            $exam->id,
            $validated['exam_category_id'],
            $termId
        );
        $exam->save();

        $detail = InPersonExamDetail::create([
            'exam_id' => $exam->id,
            'held_at' => $validated['held_at'],
            'is_descriptive' => $validated['is_descriptive'] ?? false,
            'results_visible_at' => $validated['results_visible_at'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        if (! empty($validated['results'])) {
            foreach ($validated['results'] as $result) {
                InPersonExamResult::create([
                    'in_person_exam_id' => $detail->id,
                    'user_id' => $result['user_id'],
                    'raw_score' => $result['raw_score'] ?? null,
                    'scaled_score' => $result['scaled_score'] ?? null,
                    'recorded_by' => $request->user()->id,
                    't_score' => $result['t_score'] ?? null,
                ]);
            }
        }

        if ($request->filled('class_ids')) {
            $exam->classes()->sync($request->class_ids, false);
        }

        if ($request->filled('academic_level_ids')) {
            $exam->academicLevels()->sync($request->academic_level_ids, false);
        }

        return $exam;
    }

    public function updateExam(Exam $exam, array $validated, Request $request): Exam
    {
        $exam->update($validated);

        if ($request->filled('term_id')) {
            $exam->term_id = $request->input('term_id');
        } elseif ($validated['term_id'] ?? null) {
            $exam->term_id = $validated['term_id'];
        }

        if (array_key_exists('occurrence', $validated) && $validated['occurrence'] !== null) {
            $exam->occurrence = $validated['occurrence'];
        } elseif ($exam->term_id) {
            $exam->occurrence = $this->enforceTermOccurrence(
                $exam->id,
                $exam->exam_category_id,
                $exam->term_id
            );
        }
        $exam->save();

        $this->updateDetail($exam, $request);

        if ($request->filled('class_ids')) {
            $exam->classes()->sync($request->class_ids);
        }

        if ($request->filled('academic_level_ids')) {
            $exam->academicLevels()->sync($request->academic_level_ids);
        }

        return $exam;
    }

    private function updateDetail(Exam $exam, Request $request): void
    {
        if ($exam->isInPerson()) {
            InPersonExamDetail::updateOrCreate(
                ['exam_id' => $exam->id],
                [
                    'held_at' => $request->input('held_at'),
                    'is_descriptive' => $request->boolean('is_descriptive', false),
                    'results_visible_at' => $request->input('results_visible_at'),
                    'created_by' => $request->user()->id,
                ]
            );

            return;
        }

        if (! $exam->isOnline()) {
            return;
        }

        $existingDetail = OnlineExamDetail::where('exam_id', $exam->id)->first();
        $updateData = [
            'starts_at' => $request->input('starts_at'),
            'ends_at' => $request->input('ends_at'),
            'time_limit_minutes' => $request->input('time_limit_minutes'),
            'visible_at' => $request->input('visible_at'),
            'answers_visible_at' => $request->input('answers_visible_at'),
            'created_by' => $request->user()->id,
        ];
        $oldContentPath = null;
        $oldSolutionPath = null;
        $mustDeleteOldContentFile = false;
        $mustDeleteOldSolutionFile = false;

        if ($request->has('content') || $request->hasFile('content_file')) {
            $oldContent = $existingDetail?->content;
            $oldContentPath = is_array($oldContent) ? ($oldContent['path'] ?? null) : null;
            $newContent = $this->processExamContent($request, 'content');
            $newContent = is_array($newContent) ? $newContent : [];

            if (! $request->hasFile('content_file') && $oldContentPath) {
                $newContent['path'] = $oldContentPath;
            }

            $updateData['content'] = ! empty($newContent) ? $newContent : null;
            $mustDeleteOldContentFile = $request->hasFile('content_file') && $oldContentPath;
        }

        if ($request->has('solution') || $request->hasFile('solution_file')) {
            $oldSolution = $existingDetail?->solution;
            $oldSolutionPath = is_array($oldSolution) ? ($oldSolution['path'] ?? null) : null;
            $newSolution = $this->processExamContent($request, 'solution');
            $newSolution = is_array($newSolution) ? $newSolution : [];

            if (! $request->hasFile('solution_file') && $oldSolutionPath) {
                $newSolution['path'] = $oldSolutionPath;
            }

            $updateData['solution'] = ! empty($newSolution) ? $newSolution : null;
            $mustDeleteOldSolutionFile = $request->hasFile('solution_file') && $oldSolutionPath;
        }

        OnlineExamDetail::updateOrCreate(
            ['exam_id' => $exam->id],
            $updateData
        );

        if ($mustDeleteOldContentFile) {
            Storage::disk('public')->delete($oldContentPath);
        }

        if ($mustDeleteOldSolutionFile) {
            Storage::disk('public')->delete($oldSolutionPath);
        }
    }

    private function createBooklets(OnlineExamDetail $onlineDetail, array $booklets, bool $examHasLesson): void
    {
        foreach ($booklets as $booklet) {
            OnlineExamBooklet::create([
                'online_exam_id' => $onlineDetail->id,
                'lesson_id' => $examHasLesson ? null : ($booklet['lesson_id'] ?? null),
                'title' => $booklet['title'],
                'from_question' => $booklet['from_question'] ?? null,
                'to_question' => $booklet['to_question'] ?? null,
                'booklet_scores' => $booklet['booklet_scores'] ?? null,
            ]);
        }
    }

    private function createAnswerKeys(Exam $exam, array $answerKeys): void
    {
        foreach ($answerKeys as $answerKey) {
            OnlineExamAnswerKey::create([
                'exam_id' => $exam->id,
                'question_number' => $answerKey['question_number'],
                'number_of_choices' => $answerKey['number_of_choices'] ?? 4,
                'correct_option' => $answerKey['correct_option'],
                'weight' => $answerKey['weight'] ?? 0,
                'has_negative_mark' => $answerKey['has_negative_mark'] ?? false,
                'is_active' => $answerKey['is_active'] ?? true,
            ]);
        }
    }

    private function enforceTermOccurrence(int $examId, int $categoryId, ?int $termId): ?int
    {
        if (! $termId) {
            return null;
        }

        $term = AcademicTerm::find($termId);
        $limitTermIds = [$termId];
        if ($term && $term->parent_id) {
            $limitTermIds[] = $term->parent_id;
        }

        $limit = ExamCategoryTermLimit::where('exam_category_id', $categoryId)
            ->whereIn('term_id', $limitTermIds)
            ->latest('id')
            ->first();

        $count = Exam::where('exam_category_id', $categoryId)
            ->where('term_id', $termId)
            ->where('id', '!=', $examId)
            ->count();

        if (! $limit || $limit->isUnlimited()) {
            return $count + 1;
        }

        if ($limit->max_occurrences === 0) {
            throw ValidationException::withMessages([
                'term_id' => 'برگزاری آزمون در این ترم ممنوع شده است (حداکثر ۰ بار).',
            ]);
        }

        if ($count >= $limit->max_occurrences) {
            throw ValidationException::withMessages([
                'term_id' => sprintf(
                    'تعداد برگزاری آزمون در این ترم به حداکثر (%d) رسیده است.',
                    $limit->max_occurrences
                ),
            ]);
        }

        return $count + 1;
    }

    private function enforceOnlineLessonExclusivity(array $validated): void
    {
        $examLessonSelected = ! empty($validated['lesson_id']);

        $booklets = $validated['booklets'] ?? [];
        $hasAnyBookletLesson = false;

        foreach ($booklets as $b) {
            if (! empty($b['lesson_id'])) {
                $hasAnyBookletLesson = true;
                break;
            }
        }

        if ($examLessonSelected && $hasAnyBookletLesson) {
            throw ValidationException::withMessages([
                'lesson_id' => 'انتخاب همزمان «درس آزمون» و «درس دفترچه‌ها» مجاز نیست.',
                'booklets.*.lesson_id' => 'در صورت انتخاب درس برای کل آزمون، درس دفترچه‌ها مجاز نیست.',
//                'booklets' => 'وقتی برای کل آزمون درس انتخاب می‌کنید، برای دفترچه‌ها نباید درس تعیین شود.',
            ]);
        }
    }

    private function processExamContent(Request $request, string $field): ?array
    {
        $content = $request->input($field);
        $content = is_string($content) ? json_decode($content, true) : $content;

        $fileField = $field.'_file';
        if ($request->hasFile($fileField)) {
            $file = $request->file($fileField);
            $content = $content ?? [];
            $content['path'] = $this->storeExamFile($file, $field);
            if (! isset($content['type'])) {
                $content['type'] = $file->getClientMimeType() === 'application/pdf' ? 'pdf' : 'image';
            }
        }

        return $content;
    }

    private function storeExamFile(UploadedFile $file, string $prefix = ''): string
    {
        $filename = sprintf(
            'exam_%s_%s.%s',
            $prefix,
            uniqid(),
            $file->getClientOriginalExtension()
        );

        return $file->storeAs('exam-files', $filename, 'public');
    }


    public function listStudentRelatedExams(
        int $studentUserId,
        ?string $from = null,
        ?string $to = null,
        bool $onlyOnline = true,
        bool $onlyPendingOrInProgress = true
    ): Builder
    {
        // ۱. بازیابی کلاس‌ها و پایه‌های فعال دانش‌آموز در ترم‌های جاری
        $enrollments = TermEnrollment::query()
            ->where('user_id', $studentUserId)
            ->active()
            ->whereTermIsActive()
            ->with(['schoolClass.academicLevel'])
            ->get();

        $studentClassIds = $enrollments
            ->pluck('class_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $studentLevelIds = $enrollments
            ->map(fn ($en) => $en->schoolClass?->academic_level_id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        // اگر دانش‌آموز در هیچ کلاسی/پایه‌ای ثبت‌نام فعال ندارد، دست‌خالی برگرد
        if (empty($studentClassIds) && empty($studentLevelIds)) {
            return collect();
        }

        $fromDt = $from ? Carbon::parse($from) : null;
        $toDt   = $to ? Carbon::parse($to) : null;

        // ۲. کوئری اصلی آزمون با Select امن
        $query = Exam::query()
            ->select([
                'exams.id',
                'exams.name',
                'exams.description',
                'exams.lesson_id',
                'exams.min_passing_score',
                'exams.max_score',
                'exams.delivery_mode',
                'exams.exam_category_id',
                'exams.term_id',
                'exams.occurrence',
                'exams.created_by',
                'exams.created_at',
            ])
            ->with([
                'lesson:id,name',
                'category:id,title',
                'term:id,name,school_id,is_active,starts_at,ends_at',
                // فقط اطلاعات زمان‌بندی (بدون booklets/content/solutions)
                'onlineExamDetail:exam_id,starts_at,ends_at,time_limit_minutes,visible_at,answers_visible_at',
                // اگر خواستیم وضعیت نشست فعلی دانش‌آموز رو هم توی کارت آزمون نشون بدیم (مثلاً in_progress)
                'onlineExamSessions' => function ($sQuery) use ($studentUserId) {
                    $sQuery->where('student_id', $studentUserId)
                        ->select([
                            'id',
                            'exam_id',
                            'student_id',
                            'status',
                            'started_at',
                            'submitted_at',
                            'time_used_seconds',
                            'duration_limit_seconds',
                            'attempt_number',
                        ]);
                },
            ]);

        if ($onlyOnline) {
            $query->where('delivery_mode', 'online');
        }

        // ۳. فیلتر بازه زمانی آنلاین بر اساس starts_at / ends_at
        $query->whereHas('onlineExamDetail', function ($detailQuery) use ($fromDt, $toDt) {
            if ($fromDt) {
                $detailQuery->where('ends_at', '>=', $fromDt);
            }
            if ($toDt) {
                $detailQuery->where('starts_at', '<=', $toDt);
            }
        });

        // ۴. ماتریس دسترسی سطح و کلاس (Academic Level & School Class)
        $query->where(function ($matrix) use ($studentLevelIds, $studentClassIds) {
            // سناریو الف: فقط پایه دارد (بدون قید کلاس)
            $matrix->orWhere(function ($sub) use ($studentLevelIds) {
                $sub->whereHas('academicLevels', fn ($al) => $al->whereIn('academic_levels.id', $studentLevelIds))
                    ->whereDoesntHave('classes');
            });

            // سناریو ب: فقط کلاس دارد (بدون قید پایه)
            $matrix->orWhere(function ($sub) use ($studentClassIds) {
                $sub->whereHas('classes', fn ($c) => $c->whereIn('classes.id', $studentClassIds))
                    ->whereDoesntHave('academicLevels');
            });

            // سناریو ج: هم پایه دارد و هم کلاس => اشتراک هر دو (AND)
            $matrix->orWhere(function ($sub) use ($studentLevelIds, $studentClassIds) {
                $sub->whereHas('academicLevels', fn ($al) => $al->whereIn('academic_levels.id', $studentLevelIds))
                    ->whereHas('classes', fn ($c) => $c->whereIn('classes.id', $studentClassIds));
            });
        });

        // ۵. فیلتر وضعیت پاسخگویی (حذف آزمون‌های نهایی‌شده یا منقضی‌شده)
        if ($onlyPendingOrInProgress) {
            $query->whereDoesntHave('onlineExamSessions', function ($sessionQuery) use ($studentUserId) {
                $sessionQuery->where('student_id', $studentUserId)
                    ->whereIn('status', ['submitted', 'graded', 'expired']);
            });
        }

        // ۶. سورت بر اساس نزدیک‌ترین زمان شروع
        $query->orderBy(
            \App\Models\OnlineExamDetail::select('starts_at')
                ->whereColumn('online_exam_details.exam_id', 'exams.id')
                ->limit(1),
            'asc'
        );

        return $query;
    }
}
