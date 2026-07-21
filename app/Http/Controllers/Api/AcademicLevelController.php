<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;


use App\Http\Controllers\Controller;
use App\Models\AcademicLevel;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;

class AcademicLevelController extends Controller
{
    use Filter, CommonCRUD;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:academic_levels.view')->only(['index', 'show']);
        $this->middleware('admin_or_permission:academic_levels.create')->only(['store']);
        $this->middleware('admin_or_permission:academic_levels.update')->only(['update']);
        $this->middleware('admin_or_permission:academic_levels.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => ['name'],
            'filterRelationKeys' => [
                [
                    'requestKey' => 'field_name',
                    'relationName' => 'academicField',
                    'relationColumn' => 'name',
                    'exact' => false,
                ],
            ],
            'eagerLoads' => ['school', 'academicField'],
        ];

        return $this->commonIndex($request, AcademicLevel::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|exists:schools,id',
            'field_id' => 'required|exists:academic_fields,id',
            'name' => 'required|string|max:255',
        ]);

        return $this->commonStore($request, AcademicLevel::class);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $level = AcademicLevel::with(['school', 'academicField'])->findOrFail($id);

        return $this->jsonResponseOk($level);
    }

    public function update(Request $request, AcademicLevel $academicLevel): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|exists:schools,id',
            'field_id' => 'sometimes|required|exists:academic_fields,id',
            'name' => 'sometimes|required|string|max:255',
        ]);

        return $this->commonUpdate($request, $academicLevel);
    }

    public function destroy(AcademicLevel $academicLevel): JsonResponse
    {
        return $this->commonDestroy($academicLevel);
    }
}
