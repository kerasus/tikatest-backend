<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolFeature;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SchoolFeatureController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:school.features.view')->only(['index', 'show']);
        $this->middleware('admin_or_permission:school.features.create')->only(['store']);
        $this->middleware('admin_or_permission:school.features.update')->only(['update']);
        $this->middleware('admin_or_permission:school.features.delete')->only(['destroy']);
    }

    public function index(Request $request, School $school): JsonResponse
    {
        $request->merge(['school_id' => $school->id]);

        return $this->commonIndex($request, SchoolFeature::class, [
            'filterKeysExact' => ['school_id', 'feature_key', 'is_enabled'],
        ]);
    }

    public function store(Request $request, School $school): JsonResponse
    {
        $validated = $request->validate([
            'feature_key' => [
                'required',
                Rule::in(SchoolFeature::KEYS),
                Rule::unique('school_features')->where('school_id', $school->id),
            ],
            'is_enabled' => 'sometimes|boolean',
            'settings' => 'nullable|array',
            'expires_at' => 'nullable|date',
        ]);

        $feature = $school->features()->create($validated);

        return $this->jsonResponseOk($feature);
    }

    public function show(Request $request, $school, $feature = null): JsonResponse
    {
        $school = $school instanceof School ? $school : School::findOrFail($school);
        $feature = $feature instanceof SchoolFeature
            ? $feature
            : SchoolFeature::findOrFail($feature);
        $this->ensureBelongsToSchool($school, $feature);

        return $this->jsonResponseOk($feature);
    }

    public function update(Request $request, School $school, SchoolFeature $feature): JsonResponse
    {
        $this->ensureBelongsToSchool($school, $feature);

        $validated = $request->validate([
            'feature_key' => [
                'sometimes',
                'required',
                Rule::in(SchoolFeature::KEYS),
                Rule::unique('school_features')
                    ->where('school_id', $school->id)
                    ->ignore($feature->id),
            ],
            'is_enabled' => 'sometimes|boolean',
            'settings' => 'nullable|array',
            'expires_at' => 'nullable|date',
        ]);

        $feature->update($validated);

        return $this->jsonResponseOk($feature->fresh());
    }

    public function destroy(School $school, SchoolFeature $feature): JsonResponse
    {
        $this->ensureBelongsToSchool($school, $feature);
        $feature->delete();

        return $this->jsonResponseOk(['message' => 'ویژگی مدرسه با موفقیت حذف شد.']);
    }

    private function ensureBelongsToSchool(School $school, SchoolFeature $feature): void
    {
        abort_unless($feature->school_id === $school->id, 404);
    }
}
