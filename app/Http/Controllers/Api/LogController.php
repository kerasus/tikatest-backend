<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRoleType;
use App\Http\Controllers\Controller;
use App\Services\LogViewerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LogController extends Controller
{
    public function __construct(protected LogViewerService $logService)
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:'.UserRoleType::Admin->value);
    }

    /**
     * دریافت لیست فایل‌های لاگ
     */
    public function files(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->logService->getLogFiles(),
        ]);
    }

    public function clear(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'string', Rule::in(array_column($this->logService->getLogFiles(), 'name'))],
        ]);

        $this->logService->clearLogFile($validated['file']);

        return response()->json([
            'success' => true,
            'message' => 'فایل لاگ با موفقیت خالی شد.',
        ]);
    }

    /**
     * دریافت لاگ‌های پارس شده
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['sometimes', 'string', Rule::in(array_column($this->logService->getLogFiles(), 'name'))],
            'level' => ['nullable', 'string', 'max:30'],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $filename = $validated['file'] ?? 'laravel.log';
        $filters = [
            'level'    => $validated['level'] ?? null,
            'search'   => $validated['search'] ?? null,
            'page'     => $validated['page'] ?? 1,
            'per_page' => $validated['per_page'] ?? 50,
        ];

        $result = $this->logService->getParsedLogs($filename, $filters);

        return response()->json([
            'success' => true,
            'data'    => $result,
        ]);
    }
}
