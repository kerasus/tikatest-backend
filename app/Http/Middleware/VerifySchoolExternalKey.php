<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\SchoolFeature;
use Symfony\Component\HttpFoundation\Response;

class VerifySchoolExternalKey
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-School-Service-Key') ?? $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'کلید سرویس مدرسه ارائه نشده است.',
            ], 401);
        }

        // جستجوی مستقیم روی فیلد JSON جدول school_features
        $feature = SchoolFeature::query()
            ->with('school')
            ->where('feature_key', SchoolFeature::FTP_PANEL)
            ->where('is_enabled', true)
            ->where('settings->inbound_api_key', $token)
            ->first();

        if (!$feature || !$feature->school) {
            return response()->json([
                'success' => false,
                'message' => 'کلید سرویس نامعتبر است یا سرویس غیرفعال شده است.',
            ], 401);
        }

        $request->attributes->set('current_school', $feature->school);
        $request->attributes->set('school_feature', $feature);

        return $next($request);
    }
}
