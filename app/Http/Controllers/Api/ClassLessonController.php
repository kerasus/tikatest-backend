<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassLesson;
use App\Models\Lesson;
use App\Models\SchoolClass;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClassLessonController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:classes.view')->only(['index', 'show']);
        $this->middleware('admin_or_permission:classes.create')->only(['store']);
        $this->middleware('admin_or_permission:classes.update')->only(['update']);
        $this->middleware('admin_or_permission:classes.delete')->only(['destroy']);
    }

    public function index(Request $request, $schoolClass): JsonResponse
    {
        $classId = $schoolClass instanceof SchoolClass ? $schoolClass->id : (int) $schoolClass;
        $request->merge(['class_id' => $classId]);

        $config = [
            'filterKeysExact' => ['class_id', 'lesson_id'],
            'eagerLoads' => [
                'lesson.academicLevel.academicField.school',
                'schoolClass.academicLevel.academicField.school',
            ],
        ];

        return $this->commonIndex($request, ClassLesson::class, $config);
    }

    public function store(Request $request, $schoolClass): JsonResponse
    {
        // ۱. استخراج آی‌دی کلاس (چه رشته باشد چه آبجکت)
        $classId = $schoolClass instanceof SchoolClass ? $schoolClass->id : (int) $schoolClass;

        // ۲. مرج کردن class_id در درخواست
        $request->merge(['class_id' => $classId]);

        // ۳. اعتبارسنجی
        $request->validate([
            'class_id' => 'required|exists:classes,id',
            'lesson_id' => [
                'required',
                'exists:lessons,id',
                Rule::unique('class_lesson', 'lesson_id')
                    ->where(fn ($query) => $query->where('class_id', $classId)),
            ],
        ]);

        // ۴. بررسی یکسان بودن مقطع کلاس و درس
        $this->validateLessonLevel(
            $classId,
            (int) $request->input('lesson_id')
        );

        return $this->commonStore($request, ClassLesson::class);
    }

    public function show(
        Request $request,
                $schoolClass,
                $classLesson = null
    ): JsonResponse {
        // ۱. اگر متد از داخل CommonCRUD صدا زده شده باشد، فقط یک شناسه به $schoolClass فرستاده می‌شود و $classLesson خالی است
        if ($classLesson === null) {
            $classLesson = $schoolClass instanceof ClassLesson
                ? $schoolClass
                : ClassLesson::findOrFail($schoolClass);

            $schoolClass = $classLesson->schoolClass;
        } else {
            // ۲. اگر از روت مستقیم وب صدا زده شده باشد: /api/classes/{class}/lessons/{lesson}
            $schoolClass = $schoolClass instanceof SchoolClass
                ? $schoolClass
                : SchoolClass::findOrFail($schoolClass);

            if (!$classLesson instanceof ClassLesson) {
                $classLesson = ClassLesson::findOrFail($classLesson);
            }

            // چک کردن اینکه این درس حتما مال همین کلاس باشد
            abort_unless((int) $classLesson->class_id === (int) $schoolClass->id, 404);
        }

        $classLesson->load([
            'lesson.academicLevel.academicField.school',
            'schoolClass.academicLevel.academicField.school',
        ]);

        return $this->jsonResponseOk($classLesson);
    }

    public function update(
        Request $request,
        SchoolClass $schoolClass,
        ClassLesson $classLesson
    ): JsonResponse {
        abort_unless((int) $classLesson->class_id === $schoolClass->id, 404);
        $request->merge(['class_id' => $schoolClass->id]);

        $classId = (int) $request->input('class_id', $classLesson->class_id);
        $lessonId = (int) $request->input('lesson_id', $classLesson->lesson_id);

        $request->validate([
            'class_id' => 'sometimes|required|exists:classes,id',
            'lesson_id' => [
                'sometimes',
                'required',
                'exists:lessons,id',
                Rule::unique('class_lesson', 'lesson_id')
                    ->where(fn ($query) => $query->where('class_id', $classId))
                    ->ignore($classLesson->id),
            ],
        ]);

        $this->validateLessonLevel($classId, $lessonId);

        return $this->commonUpdate($request, $classLesson);
    }

    public function destroy(SchoolClass $schoolClass, ClassLesson $classLesson): JsonResponse
    {
        abort_unless((int) $classLesson->class_id === $schoolClass->id, 404);

        return $this->commonDestroy($classLesson);
    }

    private function validateLessonLevel(int $classId, int $lessonId): void
    {
        $schoolClass = SchoolClass::findOrFail($classId);
        $lesson = Lesson::findOrFail($lessonId);

        if ($schoolClass->academic_level_id !== $lesson->academic_level_id) {
            throw ValidationException::withMessages([
                'lesson_id' => ['درس انتخاب‌شده متعلق به پایه این کلاس نیست.'],
            ]);
        }
    }
}
