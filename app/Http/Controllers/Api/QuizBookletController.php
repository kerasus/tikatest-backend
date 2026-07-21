<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;


use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizBooklet;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;

class QuizBookletController extends Controller
{
    use Filter, CommonCRUD;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('admin_or_permission:quizzes.view')->only(['index', 'show', 'indexByQuiz']);
        $this->middleware('admin_or_permission:quizzes.create')->only(['store', 'sync']);
        $this->middleware('admin_or_permission:quizzes.update')->only(['update']);
        $this->middleware('admin_or_permission:quizzes.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeysExact' => ['quiz_id'],
            'eagerLoads' => ['quiz'],
        ];

        return $this->commonIndex($request, QuizBooklet::class, $config);
    }

    public function indexByQuiz(Request $request, $quizId): JsonResponse
    {
        $quiz = Quiz::findOrFail($quizId);
        $booklets = $quiz->booklets()->get();

        return $this->jsonResponseOk($booklets);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'quiz_id' => 'required|exists:quizzes,id',
            'title' => 'required|string|max:255',
            'from_question' => 'required|integer|min:1',
            'to_question' => 'required|integer|min:1',
        ]);

        if ($data['to_question'] < $data['from_question']) {
            return $this->jsonResponseError('محدوده سوال نامعتبر است (از بزرگتر از تا باشد).', 422);
        }

        $booklet = QuizBooklet::create($data);

        return $this->jsonResponseOk($booklet);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $booklet = QuizBooklet::with('quiz')->findOrFail($id);

        return $this->jsonResponseOk($booklet);
    }

    public function update(Request $request, QuizBooklet $quizBooklet): JsonResponse
    {
        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'from_question' => 'sometimes|required|integer|min:1',
            'to_question' => 'sometimes|required|integer|min:1',
        ]);

        if (isset($data['to_question'], $data['from_question'])
            && $data['to_question'] < $data['from_question']) {
            return $this->jsonResponseError('محدوده سوال نامعتبر است (از بزرگتر از تا باشد).', 422);
        }

        $quizBooklet->update($data);

        return $this->jsonResponseOk($quizBooklet);
    }

    public function destroy(QuizBooklet $quizBooklet): JsonResponse
    {
        $quizBooklet->delete();

        return $this->jsonResponseOk(['message' => 'Booklet deleted']);
    }

    public function sync(Request $request, $quizId): JsonResponse
    {
        $quiz = Quiz::findOrFail($quizId);

        $validated = $request->validate([
            'booklets' => 'present|array',
            'booklets.*.title' => 'required|string|max:255',
            'booklets.*.from_question' => 'required|integer|min:1',
            'booklets.*.to_question' => 'required|integer|min:1',
        ]);

        foreach ($validated['booklets'] as $booklet) {
            if ($booklet['to_question'] < $booklet['from_question']) {
                return $this->jsonResponseError(
                    'محدوده سوال در دفترچه «' . $booklet['title'] . '» نامعتبر است.',
                    422
                );
            }
        }

        $incomingIds = collect($validated['booklets'])->pluck('id')->filter()->all();

        $quiz->booklets()->whereNotIn('id', $incomingIds)->delete();

        foreach ($validated['booklets'] as $booklet) {
            if (!empty($booklet['id'])) {
                $quiz->booklets()->where('id', $booklet['id'])->update([
                    'title' => $booklet['title'],
                    'from_question' => $booklet['from_question'],
                    'to_question' => $booklet['to_question'],
                ]);
            } else {
                $quiz->booklets()->create([
                    'title' => $booklet['title'],
                    'from_question' => $booklet['from_question'],
                    'to_question' => $booklet['to_question'],
                ]);
            }
        }

        return $this->jsonResponseOk($quiz->booklets()->get());
    }
}
