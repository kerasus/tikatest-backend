<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OnlineExamBooklet;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnlineExamBookletController extends Controller
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
            'filterKeys' => ['title'],
            'filterKeysExact' => ['online_exam_id', 'lesson_id'],
            'eagerLoads' => ['onlineExamDetail', 'onlineExamDetail.exam', 'lesson'],
        ];

        return $this->commonIndex($request, OnlineExamBooklet::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'online_exam_id' => 'required|exists:online_exam_details,id',
            'lesson_id' => 'nullable|exists:lessons,id',
            'title' => 'required|string|max:255',
            'from_question' => 'required|integer|min:1',
            'to_question' => 'required|integer|min:from_question',
            'booklet_scores' => 'nullable|array',
        ]);

        return $this->commonStore($request, OnlineExamBooklet::class);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $booklet = OnlineExamBooklet::with(['onlineExamDetail', 'onlineExamDetail.exam', 'lesson'])->findOrFail($id);

        return $this->jsonResponseOk($booklet);
    }

    public function update(Request $request, OnlineExamBooklet $onlineExamBooklet): JsonResponse
    {
        $request->validate([
            'online_exam_id' => 'sometimes|required|exists:online_exam_details,id',
            'lesson_id' => 'nullable|exists:lessons,id',
            'title' => 'sometimes|required|string|max:255',
            'from_question' => 'sometimes|required|integer|min:1',
            'to_question' => 'sometimes|required|integer|min:from_question',
            'booklet_scores' => 'nullable|array',
        ]);

        return $this->commonUpdate($request, $onlineExamBooklet);
    }

    public function destroy(OnlineExamBooklet $onlineExamBooklet): JsonResponse
    {
        return $this->commonDestroy($onlineExamBooklet);
    }
}
