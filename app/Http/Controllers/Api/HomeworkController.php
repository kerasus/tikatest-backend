<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRoleType;
use App\Http\Controllers\Controller;
use App\Models\Homework;
use App\Models\HomeworkAttachment;
use App\Models\HomeworkOwner;
use App\Models\HomeworkSubmission;
use App\Models\UserClass;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class HomeworkController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct()
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
            'filterKeysExact' => [
                'class_id',
                'lesson_id',
            ],
            'filterRelationKeys' => [
                [
                    'requestKey' => 'lesson_name',
                    'relationName' => 'lesson',
                    'relationColumn' => 'name',
                    'exact' => false,
                ],
                [
                    'requestKey' => 'class_name',
                    'relationName' => 'schoolClass',
                    'relationColumn' => 'name',
                    'exact' => false,
                ],
                [
                    'requestKey' => 'field_id',
                    'relationName' => 'schoolClass.academicLevel.academicField',
                    'relationColumn' => 'id',
                    'exact' => true,
                ],
                [
                    'requestKey' => 'academic_level_id',
                    'relationName' => 'schoolClass.academicLevel',
                    'relationColumn' => 'id',
                    'exact' => true,
                ],
            ],
            'eagerLoads' => ['school', 'lesson', 'schoolClass', 'createdBy', 'owners.student', 'attachments'],
        ];

        return $this->commonIndex($request, Homework::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'created_by' => 'nullable|exists:users,id',
            'content' => 'nullable|array',
            'content.*' => 'array',
            'academic_level_ids' => 'nullable|array',
            'academic_level_ids.*' => 'exists:academic_levels,id',
            'class_ids' => 'nullable|array',
            'class_ids.*' => 'exists:classes,id',
            'attachments' => 'nullable|array',
            'attachments.*.file' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,pdf',
        ]);

        return DB::transaction(function () use ($request) {
            $homework = Homework::create($request->only([
                'title',
                'description',
                'due_date',
                'created_by',
            ]));

            $this->syncAttachments($homework, $request);
            $this->syncHomeworkRelations($homework, $request);

            return $this->jsonResponseOk(
                Homework::with(['attachments', 'academicLevels', 'classes'])
                    ->findOrFail($homework->id)
            );
        });
    }

    public function show(Request $request, $id): JsonResponse
    {
        $homework = Homework::with(['createdBy', 'submissions', 'attachments', 'academicLevels', 'classes'])->findOrFail($id);

        return $this->jsonResponseOk($homework);
    }

    public function update(Request $request, Homework $homework): JsonResponse
    {
        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'created_by' => 'nullable|exists:users,id',
            'content' => 'nullable|array',
            'content.*' => 'array',
            'academic_level_ids' => 'nullable|array',
            'academic_level_ids.*' => 'exists:academic_levels,id',
            'class_ids' => 'nullable|array',
            'class_ids.*' => 'exists:classes,id',
            'attachments' => 'nullable|array',
            'attachments.*.file' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,pdf',
        ]);

        return DB::transaction(function () use ($request, $homework) {
            $homework->update($request->only([
                'title',
                'description',
                'due_date',
                'created_by',
            ]));

            $this->syncAttachments($homework, $request);
            $this->syncHomeworkRelations($homework, $request);

            return $this->jsonResponseOk(
                Homework::with(['attachments', 'academicLevels', 'classes'])
                    ->findOrFail($homework->id)
            );
        });
    }

    public function destroy(Homework $homework): JsonResponse
    {
        return $this->commonDestroy($homework);
    }

    public function myHomework(Request $request): JsonResponse
    {
        $studentId = auth()->id();
        $perPage = $request->get('length', 20);

        $homeworks = Homework::where(function ($query) use ($studentId) {
            $query->whereHas('schoolClass.userClassRegistrations', function ($q) use ($studentId) {
                $q->where('user_id', $studentId);
            })
                ->orWhereNull('class_id');
        })
            ->with(['lesson', 'schoolClass', 'owners'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->jsonResponseOk($homeworks);
    }

    public function mySubmissions(Request $request): JsonResponse
    {
        $studentId = auth()->id();
        $perPage = $request->get('length', 20);

        if (! auth()->user()->hasRole(UserRoleType::Student->value) && ! auth()->user()->hasPermissionTo('homework.view')) {
            abort(403, 'Access denied');
        }

        $submissions = HomeworkOwner::where('user_id', $studentId)
            ->with(['homework.lesson', 'homework.schoolClass'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->jsonResponseOk($submissions);
    }

    public function viewHomework(Request $request, int $homeworkId): JsonResponse
    {
        $studentId = auth()->id();

        $homework = Homework::with(['lesson', 'schoolClass', 'owners'])->findOrFail($homeworkId);

        if ($homework->class_id) {
            $isEnrolled = UserClass::where('user_id', $studentId)
                ->where('class_id', $homework->class_id)
                ->exists();

            if (! $isEnrolled) {
                abort(403, 'You are not enrolled in this class');
            }
        }

        $owner = HomeworkOwner::where('homework_id', $homeworkId)
            ->where('user_id', $studentId)
            ->first();

        if (! $owner) {
            HomeworkOwner::create([
                'homework_id' => $homeworkId,
                'user_id' => $studentId,
                'read_status' => true,
                'read_at' => now(),
            ]);
        } else {
            $owner->update([
                'read_status' => true,
                'read_at' => now(),
            ]);
        }

        return $this->jsonResponseOk([
            'homework' => $homework,
            'submission' => $owner,
        ]);
    }

    public function submitHomework(Request $request, int $homeworkId): JsonResponse
    {
        $request->validate([
            'submission_file' => 'nullable|string|max:255',
            'content' => 'nullable|array',
            'content.*' => 'array',
        ]);

        $studentId = auth()->id();

        $homework = Homework::findOrFail($homeworkId);

        if ($homework->class_id) {
            $isEnrolled = UserClass::where('user_id', $studentId)
                ->where('class_id', $homework->class_id)
                ->exists();

            if (! $isEnrolled) {
                abort(403, 'You are not enrolled in this class');
            }
        }

        if ($homework->due_date && $homework->due_date->lt(now()->startOfDay())) {
            return $this->jsonResponseError('مهلت ارسال تکلیف گذشته است.', 403);
        }

        $owner = HomeworkOwner::where('homework_id', $homeworkId)
            ->where('user_id', $studentId)
            ->first();

        if (! $owner) {
            $owner = HomeworkOwner::create([
                'homework_id' => $homeworkId,
                'user_id' => $studentId,
                'read_status' => true,
                'read_at' => now(),
            ]);
        }

        $owner->update([
            'submission_file' => $request->submission_file,
            'submitted_at' => now(),
        ]);

        $content = $request->input('content');
        if ($content) {
            HomeworkSubmission::updateOrCreate(
                [
                    'homework_id' => $homeworkId,
                    'student_id' => $studentId,
                ],
                [
                    'school_id' => $homework->school_id,
                    'content' => $content,
                    'submitted_at' => now(),
                ]
            );
        }

        return $this->jsonResponseOk($owner);
    }

    public function listSubmissions(Request $request, int $homeworkId): JsonResponse
    {
        $homework = Homework::with(['owners.student', 'submissions.student'])->findOrFail($homeworkId);

        return $this->jsonResponseOk([
            'homework' => $homework,
            'submissions' => $homework->submissions,
        ]);
    }

    public function storeAttachments(Request $request, int $homeworkId): JsonResponse
    {
        $request->validate([
            'content' => 'required|array',
            'content.*' => 'array',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $homework = Homework::findOrFail($homeworkId);

        return DB::transaction(function () use ($request, $homework) {
            $attachment = HomeworkAttachment::create([
                'homework_id' => $homework->id,
                'content' => $this->processAttachmentContent($request),
                'sort_order' => $request->input('sort_order', 0),
            ]);

            return $this->jsonResponseOk($attachment);
        });
    }

    public function destroyAttachment(int $homeworkId, int $attachmentId): JsonResponse
    {
        $attachment = HomeworkAttachment::where('homework_id', $homeworkId)
            ->where('id', $attachmentId)
            ->firstOrFail();

        $attachment->delete();

        return $this->jsonResponseOk(['message' => 'ضبط پیوست با موفقیت حذف شد.']);
    }

    protected function syncAttachments(Homework $homework, Request $request): void
    {
        $existing = $homework->attachments()->get();
        foreach ($existing as $att) {
            $att->delete();
        }

        $attachments = $request->input('attachments', []);
        if (!empty($attachments)) {
            foreach ($attachments as $index => $attachmentData) {
                $fileKey = "attachments.{$index}.file";
                $content = $attachmentData;

                if ($request->hasFile($fileKey)) {
                    $file = $request->file($fileKey);
                    $path = $this->storeHomeworkAttachmentFile($file);
                    $content = $content ?? [];
                    $content['path'] = $path;
                    $content['type'] = in_array($file->getClientMimeType(), ['application/pdf']) ? 'pdf' : 'image';
                }

                $homework->attachments()->create([
                    'content' => $content,
                    'sort_order' => $attachmentData['sort_order'] ?? $index,
                ]);
            }
        }

        $content = $request->input('content');
        if ($content) {
            $homework->attachments()->create([
                'content' => $content,
                'sort_order' => 0,
            ]);
        }
    }

    protected function syncHomeworkRelations(Homework $homework, Request $request): void
    {
        $academicLevelIds = $request->input('academic_level_ids', []);
        if (!empty($academicLevelIds)) {
            $homework->academicLevels()->sync($academicLevelIds, false);
        }

        $classIds = $request->input('class_ids', []);
        if (!empty($classIds)) {
            $homework->classes()->sync($classIds, false);
        }
    }

    protected function processAttachmentContent(Request $request): ?array
    {
        $content = $request->input('content');
        $content = is_array($content) ? $content : (is_string($content) ? json_decode($content, true) : null);

        $file = $request->file('attachment_file');
        if ($file) {
            $path = $this->storeHomeworkAttachmentFile($file);
            $content = $content ?? [];
            $content['path'] = $path;
            if (!isset($content['type'])) {
                $content['type'] = in_array($file->getClientMimeType(), ['application/pdf']) ? 'pdf' : 'image';
            }
        }

        return $content;
    }

    protected function storeHomeworkAttachmentFile(UploadedFile $file, string $prefix = 'homework'): string
    {
        $extension = $file->getClientOriginalExtension();
        $filename = sprintf('%s_%s.%s', $prefix, uniqid(), $extension);

        return $file->storeAs('homework-attachments', $filename, 'public');
    }
}
