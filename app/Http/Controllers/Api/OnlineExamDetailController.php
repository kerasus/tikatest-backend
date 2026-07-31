<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OnlineExamDetail;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnlineExamDetailController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:exams.view')->only(['index', 'show']);
        $this->middleware('admin_or_permission:exams.create')->only(['store']);
        $this->middleware('admin_or_permission:exams.update')->only(['update']);
        $this->middleware('admin_or_permission:exams.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeysExact' => ['exam_id'],
            'filterDate' => ['starts_at', 'ends_at', 'visible_at', 'answers_visible_at', 'created_at'],
            'eagerLoads' => ['exam', 'exam.category', 'exam.lesson', 'createdBy'],
        ];

        return $this->commonIndex($request, OnlineExamDetail::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'exam_id' => 'required|exists:exams,id|unique:online_exam_details,exam_id',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'time_limit_minutes' => 'nullable|integer|min:1',
            'visible_at' => 'nullable|date',
            'answers_visible_at' => 'nullable|date',
            'content' => 'nullable|array',
            'solution' => 'nullable|array',
            'created_by' => 'nullable|exists:users,id',
        ]);

        return $this->commonStore($request, OnlineExamDetail::class);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $detail = OnlineExamDetail::with(['exam', 'exam.category', 'exam.lesson', 'createdBy'])->findOrFail($id);

        return $this->jsonResponseOk($detail);
    }

    public function update(Request $request, OnlineExamDetail $onlineExamDetail): JsonResponse
    {
        $request->validate([
            'exam_id' => 'sometimes|required|exists:exams,id|unique:online_exam_details,exam_id,'.$onlineExamDetail->id,
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'time_limit_minutes' => 'nullable|integer|min:1',
            'visible_at' => 'nullable|date',
            'answers_visible_at' => 'nullable|date',
            'content' => 'nullable|array',
            'solution' => 'nullable|array',
            'created_by' => 'nullable|exists:users,id',
        ]);

        return $this->commonUpdate($request, $onlineExamDetail);
    }

    public function destroy(OnlineExamDetail $onlineExamDetail): JsonResponse
    {
        return $this->commonDestroy($onlineExamDetail);
    }
}
