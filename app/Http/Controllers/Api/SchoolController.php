<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRoleType;
use App\Http\Controllers\Controller;
use App\Models\School;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SchoolController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:'.UserRoleType::Admin->value)->only(['store', 'update', 'destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => [
                'name',
                'type',
            ],
            'filterKeysIn' => [
                'id',
            ],
            'filterKeysExact' => [
                'type',
            ],
        ];

        return $this->commonIndex($request, School::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|alpha_dash|unique:schools,slug',
            'address' => 'nullable|string',
            'website' => 'nullable|url|max:255',
            'logo' => 'nullable|file|image|mimes:jpeg,png,jpg,webp,svg|max:2048', // پیشنهاد: محدودیت سایز و فرمت استاندارد
            'type' => 'nullable|string|in:school,institute',
            'account_url' => 'nullable|string|max:255',
        ]);

        $data = $request->except('logo');

        if ($request->hasFile('logo')) {
            // ذخیره در مسیر تمیز: schools/{code}/...
            $data['logo'] = $this->storeLogo($request->file('logo'), $request->input('slug'));
        }

        $school = School::create($data);

        return $this->jsonResponseOk($school);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $school = School::findOrFail($id);

        return $this->jsonResponseOk($school);
    }

    public function update(Request $request, School $school): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'address' => 'nullable|string',
            'website' => 'nullable|url|max:255',
            'logo' => 'nullable|file|image|mimes:jpeg,png,jpg,webp,svg|max:2048',
            'type' => 'nullable|string|in:school,institute',
            'account_url' => 'nullable|string|max:255',
        ]);

        $data = $request->except('logo');

        if ($request->hasFile('logo')) {
            // ۱. حذف لوگوی قبلی در صورت وجود (جلوگیری از انباشت زباله در سرور)
            $this->deleteOldLogo($school->logo);

            // ۲. الویت با کد جدید ارسالی است، اگر نبود از کد فعلی استفاده می‌شود
            $targetSlug = $request->input('slug', $school->slug);

            $data['logo'] = $this->storeLogo($request->file('logo'), $targetSlug);
        }

        $oldSlug = $school->slug;
        $school->update($data);

        // پاکسازی کش اسلاگ قدیم و جدید (اگر تغییر کرده باشد)
        Cache::forget("school_slug_{$oldSlug}");
        if ($school->wasChanged('slug')) {
            Cache::forget("school_slug_{$school->slug}");
        }

        return $this->jsonResponseOk($school);
    }

    public function destroy(School $school): JsonResponse
    {
        return $this->commonDestroy($school);
    }

    public function getBySlug(string $slug): JsonResponse
    {
        // داده‌های ثابت برای اسلاگ‌های سیستمی و مهمان
        $staticSchools = [
            'guest' => [
                'name' => 'مهمان',
                'logo' => null,
                'type' => 'guest',
            ],
            'system' => [
                'name' => 'سامانه تیکا تست',
                'logo' => null,
                'type' => 'system',
            ],
        ];

        // اگر اسلاگ از نوع ثابت بود، بدون درگیر کردن دیتابیس و کش فوراً برمی‌گردانیم
        if (isset($staticSchools[$slug])) {
            return $this->jsonResponseOk($staticSchools[$slug]);
        }


        // کش رو با یه کلیدِ منحصر به فرد ذخیره می‌کنیم
        // مثلا: school_slug_mobtakeran
        // تایم رو هم مثلا ۱ ساعت (۳۶۰۰ ثانیه) می‌ذاریم که نه خیلی سنگین باشه نه دیتابیس رو شلوغ کنه
        $schoolData = Cache::remember("school_slug_{$slug}", 3600, function () use ($slug) {
            $school = School::where('slug', $slug)->first();

            if (! $school) {
                return null;
            }

            return [
                'name' => $school->name,
                'logo' => $school->logo,
                'type' => $school->type,
            ];
        });

        if (! $schoolData) {
            return response()->json(['message' => 'مدرسه‌ای با این مشخصات یافت نشد.'], 404);
        }

        return $this->jsonResponseOk($schoolData);
    }

    /**
     * ذخیره لوگو در مسیر ایزوله و بازگرداندن URL یا Path
     */
    protected function storeLogo(UploadedFile $file, string $schoolSlug): string
    {
        // نام‌گذاری هوشمندانه و بدون تداخل با Timestamp یا Hash
        $filename = 'logo_'.time().'.'.$file->getClientOriginalExtension();

        // مسیر دلخواه: مثلاً schools/SCH-101/logos
        $directory = "schools/{$schoolSlug}/logos";

        // ذخیره در دیسک public (یا s3 یا هرچی که توی کانفیگ داری)
        $path = $file->storeAs($directory, $filename, 'public');

        // یا اگر ترجیح می‌دی فقط Relative Path ذخیره بشه (پیشنهاد لاراول):
        return $path;
    }

    protected function deleteOldLogo(?string $logoUrl): void
    {
        if (! $logoUrl) {
            return;
        }

        // استخراج مسیر نسبی از URL (اگر URL کامل ذخیره شده است)
        $relativePath = str_replace(Storage::disk('public')->url(''), '', $logoUrl);
        $relativePath = ltrim($relativePath, '/');

        if (Storage::disk('public')->exists($relativePath)) {
            Storage::disk('public')->delete($relativePath);
        }
    }
}
