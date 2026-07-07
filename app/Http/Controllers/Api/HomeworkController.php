<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Homework;
use App\Models\HomeworkOwner;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeworkController extends Controller
{
    use Filter, CommonCRUD;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:homework.view')->only(['index', 'show']);
        $this->middleware('permission:homework.create')->only(['store']);
        $this->middleware('permission:homework.update')->only(['update']);
        $this->middleware('permission:homework.delete')->only(['destroy']);
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
            ],
            'eagerLoads' => ['school', 'lesson', 'schoolClass', 'createdBy', 'owners.student'],
        ];

        return $this->commonIndex($request, Homework::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|exists:schools,id',
            'lesson_id' => 'required|exists:lessons,id',
            'class_id' => 'nullable|exists:classes,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'attachment' => 'nullable|string|max:255',
            'attachment_2' => 'nullable|string|max:255',
            'due_date' => 'nullable|date',
            'created_by' => 'nullable|exists:users,id',
        ]);

        return $this->commonStore($request, Homework::class);
    }

    public function show(int $id): JsonResponse
    {
        $homework = Homework::with(['school', 'lesson', 'schoolClass', 'createdBy', 'owners.student', 'submissions'])->findOrFail($id);

        return $this->jsonResponseOk($homework);
    }

    public function update(Request $request, Homework $homework): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|exists:schools,id',
            'lesson_id' => 'sometimes|required|exists:lessons,id',
            'class_id' => 'nullable|exists:classes,id',
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'attachment' => 'nullable|string|max:255',
            'attachment_2' => 'nullable|string|max:255',
            'due_date' => 'nullable|date',
            'created_by' => 'nullable|exists:users,id',
        ]);

        return $this->commonUpdate($request, $homework);
    }

    public function destroy(Homework $homework): JsonResponse
    {
        return $this->commonDestroy($homework);
    }

    public function myHomework(Request $request): JsonResponse
    {
        $studentId = auth()->id();

        $homeworks = Homework::where(function ($query) use ($studentId) {
                $query->whereHas('schoolClass.studentClassRegistrations', function ($q) use ($studentId) {
                    $q->where('student_id', $studentId);
                })
                ->orWhereNull('class_id');
            })
            ->with(['lesson', 'schoolClass', 'owners'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $this->jsonResponseOk($homeworks);
    }

    public function mySubmissions(Request $request): JsonResponse
    {
        $studentId = auth()->id();

        $submissions = HomeworkOwner::where('user_id', $studentId)
            ->with(['homework.lesson', 'homework.schoolClass'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $this->jsonResponseOk($submissions);
    }

    public function viewHomework(Request $request, int $homeworkId): JsonResponse
    {
        $studentId = auth()->id();

        $homework = Homework::with(['lesson', 'schoolClass', 'owners'])->findOrFail($homeworkId);

        $owner = HomeworkOwner::where('homework_id', $homeworkId)
            ->where('user_id', $studentId)
            ->first();

        if (!$owner) {
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
        ]);

        $studentId = auth()->id();

        $homework = Homework::findOrFail($homeworkId);

        if ($homework->due_date && $homework->due_date->lt(now()->startOfDay())) {
            return $this->jsonResponseError('مهلت ارسال تکلیف گذشته است.', 403);
        }

        $owner = HomeworkOwner::where('homework_id', $homeworkId)
            ->where('user_id', $studentId)
            ->first();

        if (!$owner) {
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

        return $this->jsonResponseOk($owner);
    }

    public function listSubmissions(Request $request, int $homeworkId): JsonResponse
    {
        $homework = Homework::with(['owners.student'])->findOrFail($homeworkId);

        return $this->jsonResponseOk($homework);
    }
}