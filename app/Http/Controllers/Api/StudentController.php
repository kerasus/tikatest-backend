<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\StudentClassRegistration;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    use Filter, CommonCRUD;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:students.view')->only(['index', 'show']);
        $this->middleware('permission:students.create')->only(['store']);
        $this->middleware('permission:students.update')->only(['update']);
        $this->middleware('permission:students.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => [
                'name',
                'lastname',
                'full_name',
                'username',
                'student_phone',
                'melli_code',
                'student_code',
            ],
            'filterOnMultipleColumnKeys' => [
                [
                    'requestKey' => 'full_name_search',
                    'columns' => ['name', 'lastname', 'full_name'],
                ],
            ],
            'filterRelationKeys' => [
                [
                    'requestKey' => 'class_name',
                    'relationName' => 'studentClassRegistrations.schoolClass',
                    'relationColumn' => 'name',
                    'exact' => false,
                ],
                [
                    'requestKey' => 'field_name',
                    'relationName' => 'studentClassRegistrations.schoolClass.academicField',
                    'relationColumn' => 'name',
                    'exact' => false,
                ],
            ],
            'filterRelationIds' => [
                [
                    'requestKey' => 'class_ids',
                    'relationName' => 'studentClassRegistrations',
                    'relationNames' => ['studentClassRegistrations'],
                ],
            ],
            'eagerLoads' => [
                'studentClassRegistrations.schoolClass.academicField',
                'studentClassRegistrations.schoolClass.academicLevel',
                'roles',
                'permissions',
            ],
        ];

        $modelQuery = User::query()->where('user_type', 'student')->orWhere('user_type', null);
        $perPage = $request->has('length') ? $request->get('length') : 10;

        $this->buildFilterQuery($request, $modelQuery, User::class, $this->getConfigArray($config));

        return $this->jsonResponseOk($modelQuery->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'username' => 'required|string|unique:users,username',
            'password' => 'required|string|min:6',
            'student_phone' => 'nullable|string|max:20',
            'melli_code' => 'nullable|string|max:20',
            'student_code' => 'nullable|string|max:50',
            'birth_date' => 'nullable|date',
            'student_email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'father_name' => 'nullable|string|max:255',
            'father_phone' => 'nullable|string|max:20',
            'father_email' => 'nullable|email|max:255',
            'mother_name' => 'nullable|string|max:255',
            'mother_phone' => 'nullable|string|max:20',
            'mother_email' => 'nullable|email|max:255',
            'school_id' => 'nullable|exists:schools,id',
            'class_id' => 'nullable|exists:classes,id',
        ]);

        $data = $request->all();
        $data['user_type'] = 'student';

        $user = User::create($data);

        if ($request->filled('class_id')) {
            StudentClassRegistration::create([
                'student_id' => $user->id,
                'class_id' => $request->class_id,
                'school_id' => $request->school_id,
            ]);
        }

        return $this->jsonResponseOk($user->load('studentClassRegistrations.schoolClass'));
    }

    public function show(int $id): JsonResponse
    {
        $student = User::where('id', $id)
            ->where(function ($query) {
                $query->where('user_type', 'student')->orWhereNull('user_type');
            })
            ->with([
                'studentClassRegistrations.schoolClass.academicField',
                'studentClassRegistrations.schoolClass.academicLevel',
                'roles',
                'permissions',
            ])
            ->findOrFail($id);

        return $this->jsonResponseOk($student);
    }

    public function update(Request $request, User $student): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'lastname' => 'sometimes|required|string|max:255',
            'username' => 'sometimes|required|string|unique:users,username,' . $student->id,
            'password' => 'nullable|string|min:6',
            'student_phone' => 'nullable|string|max:20',
            'melli_code' => 'nullable|string|max:20',
            'student_code' => 'nullable|string|max:50',
            'birth_date' => 'nullable|date',
            'student_email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'father_name' => 'nullable|string|max:255',
            'father_phone' => 'nullable|string|max:20',
            'father_email' => 'nullable|email|max:255',
            'mother_name' => 'nullable|string|max:255',
            'mother_phone' => 'nullable|string|max:20',
            'mother_email' => 'nullable|email|max:255',
            'school_id' => 'nullable|exists:schools,id',
            'class_id' => 'nullable|exists:classes,id',
        ]);

        $student->fill($request->all());
        $student->save();

        if ($request->filled('class_id')) {
            StudentClassRegistration::updateOrCreate(
                ['student_id' => $student->id],
                ['class_id' => $request->class_id, 'school_id' => $request->school_id]
            );
        }

        return $this->jsonResponseOk($student->load('studentClassRegistrations.schoolClass'));
    }

    public function destroy(User $student): JsonResponse
    {
        if ($student->user_type !== 'student') {
            return $this->jsonResponseServerError([
                'errors' => ['student' => ['این کاربر دانش آموز نیست.']]
            ]);
        }

        $student->delete();

        return $this->jsonResponseOk(['message' => 'حذف دانش آموز با موفقیت انجام شد.']);
    }
}
