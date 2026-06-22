<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    protected function jsonResponseOk($data): JsonResponse
    {
        return response()->json($data, 200);
    }

    protected function jsonResponseServerError($errors): JsonResponse
    {
        return response()->json($errors, 500);
    }

    protected function jsonResponseError(string $message, int $status = 400, array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'message' => $message,
        ], $extra), $status);
    }

    public function show(Request $request, $id): JsonResponse
    {
        return new JsonResponse(['error' => 'Method not implemented'], 501);
    }
}
