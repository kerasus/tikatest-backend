<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TermEnrollment;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TermEnrollmentController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:terms.view')->only(['index', 'show']);
        $this->middleware('admin_or_permission:terms.create')->only(['store']);
        $this->middleware('admin_or_permission:terms.update')->only(['update']);
        $this->middleware('admin_or_permission:terms.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeysExact' => ['term_id', 'student_id', 'class_id', 'school_id'],
            'filterDate' => ['enrolled_at', 'left_at'],
            'eagerLoads' => ['term.school', 'student', 'class'],
        ];

        return $this->commonIndex($request, TermEnrollment::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'term_id' => 'required|exists:academic_terms,id',
            'student_id' => 'required|exists:users,id',
            'class_id' => 'nullable|exists:classes,id',
            'school_id' => 'required|exists:schools,id',
            'enrolled_at' => 'nullable|date',
            'left_at' => 'nullable|date|after:enrolled_at',
        ]);

        return $this->commonStore($request, TermEnrollment::class);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $enrollment = TermEnrollment::with(['term.school', 'student', 'class'])->findOrFail($id);

        return $this->jsonResponseOk($enrollment);
    }

    public function update(Request $request, TermEnrollment $enrollment): JsonResponse
    {
        $request->validate([
            'term_id' => 'sometimes|required|exists:academic_terms,id',
            'student_id' => 'sometimes|required|exists:users,id',
            'class_id' => 'nullable|exists:classes,id',
            'school_id' => 'sometimes|required|exists:schools,id',
            'enrolled_at' => 'nullable|date',
            'left_at' => 'nullable|date|after:enrolled_at',
        ]);

        return $this->commonUpdate($request, $enrollment);
    }

    public function destroy(TermEnrollment $enrollment): JsonResponse
    {
        return $this->commonDestroy($enrollment);
    }
}
