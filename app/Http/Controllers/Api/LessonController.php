<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonController extends Controller
{
    use CommonCRUD, Filter;

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
            'returnModelQuery' => true,
        ];

        $result = $this->commonIndex($request, Lesson::class, $config);

        if (is_array($result) && isset($result['modelQuery'])) {
            if ($request->filled('field_id')) {
                $result['modelQuery']->whereHas('academicLevel.academicField', function ($query) use ($request) {
                    $query->where('academic_fields.id', $request->get('field_id'));
                });
            }

            if ($request->filled('school_id')) {
                $result['modelQuery']->whereHas('academicLevel.academicField.school', function ($query) use ($request) {
                    $query->where('schools.id', $request->get('school_id'));
                });
            }
        }

        return $result['responseWithAttachedCollection']($result['modelQuery']);
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
