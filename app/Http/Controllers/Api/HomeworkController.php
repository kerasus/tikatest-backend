<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRoleType;
use App\Http\Controllers\Controller;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Services\HomeworkService;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class HomeworkController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct(private readonly HomeworkService $homeworkService)
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:homework.view')->only(['index', 'show', 'listSubmissions']);
        $this->middleware('admin_or_permission:homework.create')->only(['store', 'storeAttachments']);
        $this->middleware('admin_or_permission:homework.update')->only(['update', 'destroyAttachment']);
        $this->middleware('admin_or_permission:homework.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => ['title'],
            'filterDate' => ['due_date', 'created_at'],
            'filterKeysExact' => ['lesson_id', 'term_id'],
            'filterRelationIds' => [
                [
                    'requestKey' => 'lesson_id',
                    'relationName' => 'lesson',
                ],
                [
                    'requestKey' => 'term_id',
                    'relationName' => 'term',
                ],
                [
                    'requestKey' => 'class_id',
                    'relationName' => 'classes',
                ],
                [
                    'requestKey' => 'field_id',
                    'relationName' => 'academicLevels.academicField',
                ],
                [
                    'requestKey' => 'academic_level_id',
                    'relationName' => 'academicLevels',
                ],
            ],
            'filterKeysIn' => [
                'id',
            ],
            'scopes' => [
                'inSchool',
            ],
            'eagerLoads' => [
                'attachments',
                'academicLevels',
                'classes',
                'createdBy',
                'lesson',
                'term',
            ],
        ];

        return $this->commonIndex($request, Homework::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->has('attachments') && is_string($request->input('attachments'))) {
            $request->merge(['attachments' => json_decode($request->input('attachments'), true)]);
        }

        if ($request->has('academic_level_ids') && is_string($request->input('academic_level_ids'))) {
            $request->merge(['academic_level_ids' => json_decode($request->input('academic_level_ids'), true)]);
        }

        if ($request->has('class_ids') && is_string($request->input('class_ids'))) {
            $request->merge(['class_ids' => json_decode($request->input('class_ids'), true)]);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'lesson_id' => 'nullable|exists:lessons,id',
            'term_id' => 'required|exists:academic_terms,id',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'academic_level_ids' => 'nullable|array',
            'academic_level_ids.*' => 'exists:academic_levels,id',
            'class_ids' => 'nullable|array',
            'class_ids.*' => 'exists:classes,id',
            'attachments' => 'nullable|array',
            'attachments.*.file' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,pdf',
        ]);

        return $this->jsonResponseOk($this->homeworkService->create($request));
    }

    public function show(Request $request, $id): JsonResponse
    {
        $homework = Homework::with([
            'createdBy',
            'lesson',
            'term',
            'submissions',
            'attachments',
            'academicLevels',
            'classes',
        ])->findOrFail($id);

        return $this->jsonResponseOk($homework);
    }

    public function update(Request $request, Homework $homework): JsonResponse
    {
        if ($request->has('attachments') && is_string($request->input('attachments'))) {
            $request->merge(['attachments' => json_decode($request->input('attachments'), true)]);
        }

        if ($request->has('academic_level_ids') && is_string($request->input('academic_level_ids'))) {
            $request->merge(['academic_level_ids' => json_decode($request->input('academic_level_ids'), true)]);
        }

        if ($request->has('class_ids') && is_string($request->input('class_ids'))) {
            $request->merge(['class_ids' => json_decode($request->input('class_ids'), true)]);
        }

        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'lesson_id' => 'nullable|exists:lessons,id',
            'term_id' => 'sometimes|required|exists:academic_terms,id',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'academic_level_ids' => 'nullable|array',
            'academic_level_ids.*' => 'exists:academic_levels,id',
            'class_ids' => 'nullable|array',
            'class_ids.*' => 'exists:classes,id',
            'attachments' => 'nullable|array',
            'attachments.*.file' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,pdf',
        ]);

        return $this->jsonResponseOk($this->homeworkService->update($request, $homework));
    }

    public function destroy(Homework $homework): JsonResponse
    {
        $this->homeworkService->deleteAttachments($homework);

        return $this->commonDestroy($homework);
    }

    public function myHomeworks(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'school_id' => [
                'required',
                'integer',
                'exists:schools,id',
                Rule::in($this->homeworkService->studentSchoolIds($user)),
            ],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'due_date_since_date' => ['nullable', 'date'],
            'due_date_till_date' => ['nullable', 'date', 'after_or_equal:due_date_since_date'],
            'title' => ['nullable', 'string'],
            'lesson_id' => ['nullable', 'integer', 'exists:lessons,id'],
            'term_id' => ['nullable', 'integer', 'exists:academic_terms,id'],
            'status' => ['nullable', Rule::in(['pending', 'submitted', 'overdue'])],
            'id_in' => ['nullable', 'array'],
            'id_in.*' => ['integer'],
            'length' => ['nullable', 'integer', 'min:1'],
        ], [
            'school_id.required' => 'شناسه مدرسه الزامی است.',
            'school_id.exists'   => 'مدرسه انتخاب شده نامعتبر است.',
            'school_id.in'       => 'شما به دسته‌بندی‌های این مدرسه دسترسی ندارید.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }
        $homeworks = $this->homeworkService->studentHomeworksQuery($user, $validator->validated())
            ->paginate($request->integer('length', 10));

        return $this->jsonResponseOk($homeworks);
    }

    public function mySubmissions(Request $request): JsonResponse
    {
        $studentId = auth()->id();
        $perPage = $request->get('length', 20);

        if (! auth()->user()->hasRole(UserRoleType::Student->value) && ! auth()->user()->hasPermissionTo('homework.view')) {
            abort(403, 'Access denied');
        }

        $submissions = HomeworkSubmission::where('student_id', $studentId)
            ->with(['homework.lesson', 'homework.schoolClass'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->jsonResponseOk($submissions);
    }

    public function viewHomework(Request $request, int $homeworkId): JsonResponse
    {
        return $this->jsonResponseOk($this->homeworkService->viewForStudent($homeworkId, $request->user()->id));
    }

    public function submitHomework(Request $request, int $homeworkId): JsonResponse
    {
        $request->validate([
            'submission_file' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,pdf',
            'content' => 'nullable',
        ]);

        $submission = $this->homeworkService->submit($request, $homeworkId);
        if ($submission === null) {
            return $this->jsonResponseError('مهلت ارسال تکلیف گذشته است.', 403);
        }

        return $this->jsonResponseOk($submission);
    }

    public function storeAttachments(Request $request, int $homeworkId): JsonResponse
    {
        $request->validate([
            'content' => 'required|array',
            'content.*' => 'array',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $homework = Homework::findOrFail($homeworkId);

        return $this->jsonResponseOk($this->homeworkService->createAttachment($request, $homework));
    }

    public function destroyAttachment(int $homeworkId, int $attachmentId): JsonResponse
    {
        $this->homeworkService->deleteAttachment($homeworkId, $attachmentId);

        return $this->jsonResponseOk([
            'message' => 'پیوست با موفقیت حذف شد.',
        ]);
    }

}
