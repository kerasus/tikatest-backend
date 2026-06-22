<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Homework;
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
            'eagerLoads' => ['school', 'lesson', 'schoolClass', 'createdBy'],
        ];

        return $this->commonIndex($request, Homework::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|exists:schools,id',
            'lesson_id' => 'required|exists:lessons,id',
            'class_id' => 'required|exists:classes,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'attachment' => 'nullable|string|max:255',
            'due_date' => 'nullable|date',
            'created_by' => 'nullable|exists:users,id',
        ]);

        return $this->commonStore($request, Homework::class);
    }

    public function show(int $id): JsonResponse
    {
        $homework = Homework::with(['school', 'lesson', 'schoolClass', 'createdBy', 'submissions'])->findOrFail($id);

        return $this->jsonResponseOk($homework);
    }

    public function update(Request $request, Homework $homework): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|exists:schools,id',
            'lesson_id' => 'sometimes|required|exists:lessons,id',
            'class_id' => 'sometimes|required|exists:classes,id',
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'attachment' => 'nullable|string|max:255',
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

        $homeworks = Homework::whereHas('schoolClass.studentClassRegistrations', function ($q) use ($studentId) {
            $q->where('student_id', $studentId);
        })
            ->with(['lesson', 'schoolClass', 'submissions'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $this->jsonResponseOk($homeworks);
    }

    public function mySubmissions(Request $request): JsonResponse
    {
        $studentId = auth()->id();

        $submissions = \App\Models\HomeworkSubmission::where('student_id', $studentId)
            ->with(['homework.lesson', 'homework.schoolClass'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $this->jsonResponseOk($submissions);
    }
}
