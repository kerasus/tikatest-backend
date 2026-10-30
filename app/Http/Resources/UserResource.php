<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'username' => $this->username,
            'national_id' => $this->national_id,
            'mobile' => $this->mobile,
            'bale_username' => $this->bale_username ?? null,
            'bale_chat_id' => $this->bale_chat_id ?? null,
            // اگر از spatie/laravel-permission استفاده می‌کنی:
            'roles' => $this->whenLoaded('roles'),
            'schools' => $this->whenLoaded('schools'),
            'permissions' => $this->whenLoaded('permissions'),
            'term_enrollments' => $this->whenLoaded('termEnrollments'),
            // یا اگر فیلدهای دیگه‌ای داری اینجا مپ می‌شن
        ];
    }
}
