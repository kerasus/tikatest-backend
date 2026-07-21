<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;


use App\Http\Controllers\Controller;
use App\Models\StudentClassRegistration;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;

class StudentClassRegistrationController extends Controller
{
    use Filter, CommonCRUD;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:student_registrations.view')->only(['index', 'show']);
        $this->middleware('admin_or_permission:student_registrations.create')->only(['store']);
        $this->middleware('admin_or_permission:student_registrations.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterRelationIds' => [
                [
                    'requestKey' => 'student_ids',
                    'relationName' => 'student',
                ],
                [
                    'requestKey' => 'class_ids',
                    'relationName' => 'schoolClass',
                ],
            ],
            'eagerLoads' => ['student', 'schoolClass', 'school'],
        ];

        return $this->commonIndex($request, StudentClassRegistration::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => 'required|exists:users,id',
            'class_id' => 'required|exists:classes,id',
            'school_id' => 'nullable|exists:schools,id',
        ]);

        $registration = StudentClassRegistration::create($request->all());

        return $this->jsonResponseOk($registration->load(['student', 'schoolClass', 'school']));
    }

    public function show(Request $request, $id): JsonResponse
    {
        $registration = StudentClassRegistration::with(['student', 'schoolClass', 'school'])->findOrFail($id);

        return $this->jsonResponseOk($registration);
    }

    public function destroy(StudentClassRegistration $registration): JsonResponse
    {
        return $this->commonDestroy($registration);
    }
}
