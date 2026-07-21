<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PreRegistration;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PreRegistrationController extends Controller
{
    use Filter, CommonCRUD;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:pre_registrations.view')->only(['index']);
        $this->middleware('admin_or_permission:pre_registrations.create')->only(['store']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => [
                'username',
                'parent_username',
            ],
        ];

        return $this->commonIndex($request, PreRegistration::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string|max:255',
            'parent_username' => 'required|string|max:255',
            'password' => 'required|string|max:255',
            'sms_id' => 'nullable|string|max:255',
        ]);

        $registration = PreRegistration::create($request->all());

        return $this->jsonResponseOk($registration);
    }
}
