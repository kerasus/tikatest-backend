<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;


use App\Http\Controllers\Controller;
use App\Models\DisciplinaryRecord;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;

class DisciplinaryRecordController extends Controller
{
    use Filter, CommonCRUD;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:disciplinary_records.view')->only(['index', 'show']);
        $this->middleware('admin_or_permission:disciplinary_records.create')->only(['store']);
        $this->middleware('admin_or_permission:disciplinary_records.update')->only(['update']);
        $this->middleware('admin_or_permission:disciplinary_records.delete')->only(['destroy']);
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

    public function show(Request $request, $id): JsonResponse
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

    public function registerAbsenteeism(Request $request): JsonResponse
    {
        $request->validate([
            'student_ids' => 'required|array',
            'student_ids.*' => 'exists:users,id',
            'date' => 'required|date',
            'description' => 'nullable|string',
            'case_id' => 'required|exists:disciplinary_cases,id',
        ]);

        $createdRecords = [];
        foreach ($request->student_ids as $studentId) {
            $record = DisciplinaryRecord::create([
                'student_id' => $studentId,
                'case_id' => $request->case_id,
                'incident_date' => $request->date,
                'description' => $request->description,
                'recorded_by' => auth()->id(),
            ]);
            $createdRecords[] = $record;
        }

        return $this->jsonResponseOk($createdRecords);
    }

    public function viewAbsences(Request $request): JsonResponse
    {
        $query = DisciplinaryRecord::whereHas('disciplinaryCase', function ($q) {
            $q->where('name', 'like', '%غیبت%')->orWhere('name', 'like', '%absence%');
        })->with(['student', 'disciplinaryCase', 'recordedBy']);

        $query->when($request->filled('date_from'), function ($q) use ($request) {
            $q->where('incident_date', '>=', $request->date_from);
        });

        $query->when($request->filled('date_to'), function ($q) use ($request) {
            $q->where('incident_date', '<=', $request->date_to);
        });

        $query->when($request->filled('class_id'), function ($q) use ($request) {
            $q->whereHas('student.studentClassRegistrations', function ($subQ) use ($request) {
                $subQ->where('class_id', $request->class_id);
            });
        });

        $records = $query->orderBy('incident_date', 'desc')->paginate(20);

        return $this->jsonResponseOk($records);
    }
}
