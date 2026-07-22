<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;


use App\Http\Controllers\Controller;
use App\Models\School;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;

class SchoolController extends Controller
{
    use Filter, CommonCRUD;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:schools.view')->only(['index', 'show']);
        $this->middleware('admin_or_permission:schools.create')->only(['store']);
        $this->middleware('admin_or_permission:schools.update')->only(['update']);
        $this->middleware('admin_or_permission:schools.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => [
                'code',
                'name',
                'type',
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
            'code' => 'required|string|max:20|unique:schools,code',
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'website' => 'nullable|url|max:255',
            'logo' => 'nullable|file|mimetypes:image/*',
            'type' => 'nullable|string|in:school,institute',
            'account_url' => 'nullable|string|max:255',
        ]);

        $data = $request->except('logo');

        if ($request->hasFile('logo')) {
            $data['logo_url'] = $this->storeLogo($request->file('logo'), $request->input('code'));
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
        $request->validate([
            'code' => 'sometimes|required|string|max:20|unique:schools,code,' . $school->id,
            'name' => 'sometimes|required|string|max:255',
            'address' => 'nullable|string',
            'website' => 'nullable|url|max:255',
            'logo' => 'nullable|file|mimetypes:image/*',
            'type' => 'nullable|string|in:school,institute',
            'account_url' => 'nullable|string|max:255',
        ]);

        $data = $request->except('logo');

        if ($request->hasFile('logo')) {
            $data['logo_url'] = $this->storeLogo($request->file('logo'), $school->code ?? 'default');
        }

        $school->update($data);

        return $this->jsonResponseOk($school);
    }

    public function destroy(School $school): JsonResponse
    {
        return $this->commonDestroy($school);
    }

    private function storeLogo(\Illuminate\Http\UploadedFile $file, ?string $schoolCode): string
    {
        $extension = $file->getClientOriginalExtension();
        $schoolCode = $schoolCode ?: 'default';
        $filename = sprintf('%s_%s.%s', $schoolCode, time(), $extension);
        $directory = 'school-logos';

        return $file->storeAs($directory, $filename, 'public');
    }
}
