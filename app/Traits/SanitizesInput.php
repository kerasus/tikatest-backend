<?php

namespace App\Traits;

use Illuminate\Http\Request;

trait SanitizesInput
{
    /**
     * اعداد فارسی و عربی را به انگلیسی تبدیل می‌کند.
     */
    public function convertNumbersToEnglish(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        $value = str_replace($persian, $english, $value);
        return str_replace($arabic, $english, $value);
    }

    /**
     * پاکسازی کاراکترهای مخفی و کنترلی (BOM, ZWSP, LTR/RTL marks, Non-breaking space)
     */
    public function stripInvisibleCharacters(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // کاراکترهای مخفی: BOM (\x{FEFF})، ZWSP و کاراکترهای جهت متن LTR/RTL (\x{200B}-\x{200F}, \x{202A}-\x{202E})
        // و فاصلهٔ نشکن اکسل (\x{00A0} یا NBSP)
        $clean = preg_replace('/[\x{00A0}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{FEFF}]/u', '', $value);

        // حذف کلیه کاراکترهای نامرئی/کنترلی یونیکد (به جز خط جدید/تب در صورت نیاز)
        return preg_replace('/[\p{C}]/u', '', $clean);
    }

    /**
     * پاکسازی فیلدهای اعتبارسنجی و کدها:
     * - تبدیل اعداد فارسی/عربی به انگلیسی
     * - حذف همهٔ کاراکترهای مخفی و کنترلی
     * - حذف فاصله‌های سفید ابتدا و انتها (یا حذف کل فواصل)
     */
    public function sanitizeCredential(?string $value, bool $stripAllWhitespace = true): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = $this->convertNumbersToEnglish($value);
        $clean = $this->stripInvisibleCharacters($clean);

        if ($stripAllWhitespace) {
            // حذف کامل هرگونه فاصله درون نام‌کاربری، پسورد، کدملی و موبایل
            return preg_replace('/\s+/u', '', $clean);
        }

        return trim($clean);
    }

    /**
     * پاکسازی فیلدهای متنی فارسی (نام، نام خانوادگی، توضیحات، آدرس):
     * - اصلاح ی و ک عربی به فارسی
     * - حذف کاراکترهای مخفی اکسل
     * - فشرده‌سازی فاصله‌های سفید چندگانه به یک فاصله
     */
    public function sanitizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // اصلاح ی و ک عربی
        $value = str_replace(['ي', 'ك'], ['ی', 'ک'], $value);

        // حذف کاراکترهای مخفی خرابکار اکسل
        $clean = $this->stripInvisibleCharacters($value);

        // تبدیل چند اسپیس متوالی به یک اسپیس و تریم دو طرف
        $clean = preg_replace('/[ \t]+/u', ' ', $clean);

        return trim($clean);
    }

    /**
     * متد جادویی کمکی برای پاکسازی دسته‌جمعی فیلدهای ریکوئست قبل از ولیدیشن
     *
     * @param Request $request
     * @param array $credentialFields فیلدهایی که باید کد/انگلیسی و بدون اسپیس شوند
     * @param array $textFields فیلدهایی که متن فارسی هستند
     */
    public function sanitizeRequestInputs(Request $request, array $credentialFields = [], array $textFields = []): void
    {
        $sanitized = [];

        foreach ($credentialFields as $field) {
            if ($request->has($field) && is_string($request->input($field))) {
                $sanitized[$field] = $this->sanitizeCredential($request->input($field));
            }
        }

        foreach ($textFields as $field) {
            if ($request->has($field) && is_string($request->input($field))) {
                $sanitized[$field] = $this->sanitizeText($request->input($field));
            }
        }

        if (! empty($sanitized)) {
            $request->merge($sanitized);
        }
    }
}
