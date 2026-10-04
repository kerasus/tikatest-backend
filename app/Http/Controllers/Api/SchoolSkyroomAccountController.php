<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolSkyroomAccount;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolSkyroomAccountController extends Controller
{
    use CommonCRUD, Filter;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:skyroom.accounts.view')->only(['index', 'show']);
        $this->middleware('admin_or_permission:skyroom.accounts.create')->only(['store']);
        $this->middleware('admin_or_permission:skyroom.accounts.update')->only(['update']);
        $this->middleware('admin_or_permission:skyroom.accounts.delete')->only(['destroy']);
    }

    public function index(Request $request, School $school): JsonResponse
    {
        $request->merge(['school_id' => $school->id]);

        return $this->commonIndex($request, SchoolSkyroomAccount::class, [
            'filterKeys' => ['title', 'username'],
            'filterKeysExact' => ['school_id', 'is_active'],
            'eagerLoads' => ['rooms:id,skyroom_account_id'],
        ]);
    }

    public function store(Request $request, School $school): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'username' => 'required|string|max:255',
            'api_key' => 'required|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $account = $school->skyroomAccounts()->create($validated);

        return $this->jsonResponseOk($account);
    }

    public function show(Request $request, $school, $skyroomAccount = null): JsonResponse
    {
        $school = $school instanceof School ? $school : School::findOrFail($school);
        $skyroomAccount = $skyroomAccount instanceof SchoolSkyroomAccount
            ? $skyroomAccount
            : SchoolSkyroomAccount::findOrFail($skyroomAccount);
        $this->ensureBelongsToSchool($school, $skyroomAccount);

        return $this->jsonResponseOk($skyroomAccount->loadCount('rooms'));
    }

    public function update(
        Request $request,
        School $school,
        SchoolSkyroomAccount $skyroomAccount
    ): JsonResponse {
        $this->ensureBelongsToSchool($school, $skyroomAccount);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'username' => 'sometimes|required|string|max:255',
            'api_key' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        if (empty($validated['api_key'])) {
            unset($validated['api_key']);
        }

        $skyroomAccount->update($validated);

        return $this->jsonResponseOk($skyroomAccount->fresh()->loadCount('rooms'));
    }

    public function destroy(School $school, SchoolSkyroomAccount $skyroomAccount): JsonResponse
    {
        $this->ensureBelongsToSchool($school, $skyroomAccount);
        $skyroomAccount->delete();

        return $this->jsonResponseOk(['message' => 'اکانت اسکای‌روم با موفقیت حذف شد.']);
    }

    private function ensureBelongsToSchool(School $school, SchoolSkyroomAccount $account): void
    {
        abort_unless($account->school_id === $school->id, 404);
    }
}
