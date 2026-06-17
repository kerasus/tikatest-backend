<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DisciplinaryRecord;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisciplinaryRecordController extends Controller
{
    use Filter, CommonCRUD;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:disciplinary_records.view')->only(['index', 'show']);
        $this->middleware('permission:disciplinary_records.create')->only(['store']);
        $this->middleware('permission:disciplinary_records.update')->only(['update']);
        $this->middleware('permission:disciplinary_records.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterDate' => ['incident_date', 'created_at'],
            'filterRelationKeys' => [
                [
                    'requestKey' => 'student_name',
                    'relationName' => 'student',
                    'relationColumn' => 'full_name',
                    'exact' => false,
                ],
                [
                    'requestKey' => 'case_name',
                    'relationName' => 'disciplinaryCase',
                    'relationColumn' => 'name',
                    'exact' => false,
                ],
            ],
            'eagerLoads' => ['school', 'student', 'disciplinaryCase', 'recordedBy'],
        ];

        return $this->commonIndex($request, DisciplinaryRecord::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|exists:schools,id',
            'student_id' => 'required|exists:users,id',
            'case_id' => 'required|exists:disciplinary_cases,id',
            'description' => 'nullable|string',
            'incident_date' => 'required|date',
            'recorded_by' => 'nullable|exists:users,id',
        ]);

        return $this->commonStore($request, DisciplinaryRecord::class);
    }

    public function show(int $id): JsonResponse
    {
        $record = DisciplinaryRecord::with(['school', 'student', 'disciplinaryCase', 'recordedBy'])->findOrFail($id);

        return $this->jsonResponseOk($record);
    }

    public function update(Request $request, DisciplinaryRecord $record): JsonResponse
    {
        $request->validate([
            'school_id' => 'nullable|exists:schools,id',
            'student_id' => 'sometimes|required|exists:users,id',
            'case_id' => 'sometimes|required|exists:disciplinary_cases,id',
            'description' => 'nullable|string',
            'incident_date' => 'sometimes|required|date',
            'recorded_by' => 'nullable|exists:users,id',
        ]);

        return $this->commonUpdate($request, $record);
    }

    public function destroy(DisciplinaryRecord $record): JsonResponse
    {
        return $this->commonDestroy($record);
    }
}
