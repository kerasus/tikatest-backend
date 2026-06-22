<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\QuizParticipationToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:255',
        ]);

        $user = User::query()->where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['اطلاعات ورود نادرست است.'],
            ]);
        }

        $token = $user->createToken($request->input('device_name', 'api-token'))->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->load('roles', 'permissions'),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'خروج با موفقیت انجام شد.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->load('roles', 'permissions')
        );
    }

    public function verifyQuizToken(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $token = QuizParticipationToken::where('token', $request->token)
            ->where('expires_at', '>', now())
            ->whereNull('used_at')
            ->with(['quiz', 'student'])
            ->first();

        if (!$token) {
            return response()->json(['message' => 'Invalid or expired token'], 404);
        }

        $token->update(['used_at' => now()]);

        return response()->json([
            'quiz_id' => $token->quiz_id,
            'student_id' => $token->student_id,
            'quiz' => $token->quiz,
            'student' => $token->student,
        ]);
    }
}
