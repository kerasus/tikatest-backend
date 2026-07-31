<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicField;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcademicFieldController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:academic_fields.view')->only(['index', 'show']);
        $this->middleware('admin_or_permission:academic_fields.create')->only(['store']);
        $this->middleware('admin_or_permission:academic_fields.update')->only(['update']);
        $this->middleware('admin_or_permission:academic_fields.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => ['name'],
            'filterKeysExact' => ['school_id'],
            'filterRelationKeys' => [
                [
                    'requestKey' => 'level_name',
                    'relationName' => 'academicLevels',
                    'relationColumn' => 'name',
                    'exact' => false,
                ],
            ],
            'eagerLoads' => ['school', 'academicLevels'],
        ];

        return $this->commonIndex($request, AcademicField::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|exists:schools,id',
            'name' => 'required|string|max:255',
        ]);

        return $this->commonStore($request, AcademicField::class);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $field = AcademicField::with(['school', 'academicLevels'])->findOrFail($id);

        return $this->jsonResponseOk($field);
    }

    public function update(Request $request, AcademicField $academicField): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|exists:schools,id',
            'name' => 'sometimes|required|string|max:255',
        ]);

        return $this->commonUpdate($request, $academicField);
    }

    public function destroy(AcademicField $academicField): JsonResponse
    {
        return $this->commonDestroy($academicField);
    }
}
