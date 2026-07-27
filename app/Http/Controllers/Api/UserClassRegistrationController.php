<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;


use App\Http\Controllers\Controller;
use App\Models\UserClassRegistration;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;

class UserClassRegistrationController extends Controller
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
                    'requestKey' => 'user_ids',
                    'relationName' => 'user',
                ],
                [
                    'requestKey' => 'class_ids',
                    'relationName' => 'schoolClass',
                ],
            ],
            'eagerLoads' => ['user', 'schoolClass', 'school'],
        ];

        return $this->commonIndex($request, UserClassRegistration::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'class_id' => 'required|exists:classes,id',
            'school_id' => 'nullable|exists:schools,id',
        ]);

        $registration = UserClassRegistration::create($request->all());

        return $this->jsonResponseOk($registration->load(['user', 'schoolClass', 'school']));
    }

    public function show(Request $request, $id): JsonResponse
    {
        $registration = UserClassRegistration::with(['user', 'schoolClass', 'school'])->findOrFail($id);

        return $this->jsonResponseOk($registration);
    }

    public function destroy(UserClassRegistration $registration): JsonResponse
    {
        return $this->commonDestroy($registration);
    }
}
