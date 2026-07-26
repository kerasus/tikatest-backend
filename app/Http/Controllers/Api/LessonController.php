<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;


use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;

class LessonController extends Controller
{
    use Filter, CommonCRUD;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:lessons.view')->only(['index', 'show']);
        $this->middleware('admin_or_permission:lessons.create')->only(['store']);
        $this->middleware('admin_or_permission:lessons.update')->only(['update']);
        $this->middleware('admin_or_permission:lessons.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => ['name'],
            'filterKeysExact' => ['level_id'],
            'filterRelationKeys' => [
                [
                    'requestKey' => 'level_name',
                    'relationName' => 'academicLevel',
                    'relationColumn' => 'name',
                    'exact' => false,
                ],
            ],
            'eagerLoads' => ['academicLevel'],
        ];

        return $this->commonIndex($request, Lesson::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'level_id' => 'nullable|exists:academic_levels,id',
            'coefficient' => 'nullable|numeric|min:0',
        ]);

        return $this->commonStore($request, Lesson::class);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $lesson = Lesson::with(['academicLevel'])->findOrFail($id);

        return $this->jsonResponseOk($lesson);
    }

    public function update(Request $request, Lesson $lesson): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'level_id' => 'nullable|exists:academic_levels,id',
            'coefficient' => 'nullable|numeric|min:0',
        ]);

        return $this->commonUpdate($request, $lesson);
    }

    public function destroy(Lesson $lesson): JsonResponse
    {
        return $this->commonDestroy($lesson);
    }
}
