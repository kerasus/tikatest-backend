<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamSession;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamSessionController extends Controller
{
    use Filter, CommonCRUD;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:exam_sessions.view')->only(['index', 'show']);
        $this->middleware('permission:exam_sessions.create')->only(['store', 'bulkStore']);
        $this->middleware('permission:exam_sessions.update')->only(['update']);
        $this->middleware('permission:exam_sessions.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => ['grade_type'],
            'filterKeysExact' => [
                'is_report_card',
                'is_descriptive',
            ],
            'filterDate' => [
                'exam_date',
                'created_at',
            ],
            'filterRelationKeys' => [
                [
                    'requestKey' => 'lesson_name',
                    'relationName' => 'lesson',
                    'relationColumn' => 'name',
                    'exact' => false,
                ],
            ],
            'eagerLoads' => ['school', 'lesson', 'schoolClass', 'createdBy', 'quizSession'],
        ];

        return $this->commonIndex($request, ExamSession::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|exists:schools,id',
            'lesson_id' => 'required|exists:lessons,id',
            'class_id' => 'required|exists:classes,id',
            'exam_date' => 'required|date',
            'grade_type' => 'required|string|in:class_quiz,monthly_quiz,mid_term_1,continuous_1,final_1,mid_term_2,continuous_2,final_2,other',
            'grade_name_for_other_type' => 'nullable|string|max:255',
            'is_descriptive' => 'boolean',
            'is_report_card' => 'boolean',
            'quiz_session_id' => 'nullable|exists:quiz_sessions,id',
            'min_grade' => 'nullable|numeric|min:0',
            'created_by' => 'nullable|exists:users,id',
        ]);

        return $this->commonStore($request, ExamSession::class);
    }

    public function show(int $id): JsonResponse
    {
        $session = ExamSession::with(['school', 'lesson', 'schoolClass', 'createdBy', 'quizSession'])->findOrFail($id);

        return $this->jsonResponseOk($session);
    }

    public function update(Request $request, ExamSession $examSession): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|exists:schools,id',
            'lesson_id' => 'sometimes|required|exists:lessons,id',
            'class_id' => 'sometimes|required|exists:classes,id',
            'exam_date' => 'sometimes|required|date',
            'grade_type' => 'sometimes|required|string|in:class_quiz,monthly_quiz,mid_term_1,continuous_1,final_1,mid_term_2,continuous_2,final_2,other',
            'grade_name_for_other_type' => 'nullable|string|max:255',
            'is_descriptive' => 'boolean',
            'is_report_card' => 'boolean',
            'quiz_session_id' => 'nullable|exists:quiz_sessions,id',
            'min_grade' => 'nullable|numeric|min:0',
            'created_by' => 'nullable|exists:users,id',
        ]);

        return $this->commonUpdate($request, $examSession);
    }

    public function destroy(ExamSession $examSession): JsonResponse
    {
        return $this->commonDestroy($examSession);
    }

    public function bulkStore(Request $request): JsonResponse
    {
        $request->validate([
            'sessions' => 'required|array',
            'sessions.*.lesson_id' => 'required|exists:lessons,id',
            'sessions.*.class_id' => 'required|exists:classes,id',
            'sessions.*.exam_date' => 'required|date',
            'sessions.*.grade_type' => 'required|string|in:class_quiz,monthly_quiz,mid_term_1,continuous_1,final_1,mid_term_2,continuous_2,final_2,other',
            'sessions.*.grade_name_for_other_type' => 'nullable|string|max:255',
            'sessions.*.is_descriptive' => 'boolean',
            'sessions.*.is_report_card' => 'boolean',
            'sessions.*.quiz_session_id' => 'nullable|exists:quiz_sessions,id',
            'sessions.*.min_grade' => 'nullable|numeric|min:0',
            'school_id' => 'nullable|exists:schools,id',
        ]);

        $createdSessions = [];
        foreach ($request->sessions as $sessionData) {
            $sessionData['created_by'] = $request->user()->id;
            $session = ExamSession::create($sessionData);
            $createdSessions[] = $session;
        }

        return $this->jsonResponseOk($createdSessions);
    }

    public function participants(Request $request, $sessionId): JsonResponse
    {
        $session = ExamSession::with(['school', 'lesson', 'schoolClass', 'createdBy', 'quizSession', 'grades.student'])->findOrFail($sessionId);

        return $this->jsonResponseOk($session);
    }
}