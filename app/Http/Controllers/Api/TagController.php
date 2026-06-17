<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TagController extends Controller
{
    use Filter, CommonCRUD;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:tags.view')->only(['index', 'show']);
        $this->middleware('permission:tags.create')->only(['store']);
        $this->middleware('permission:tags.update')->only(['update']);
        $this->middleware('permission:tags.delete')->only(['destroy']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => [
                'name',
                'slug',
                'description',
            ],
            'filterKeysExact' => [
                'color',
            ],
            'eagerLoads' => ['places'],
        ];

        return $this->commonIndex($request, Tag::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:tags,slug',
            'color' => 'nullable|string|max:50',
            'description' => 'nullable|string',
        ]);

        if (!$request->filled('slug')) {
            $request->merge(['slug' => Str::slug($request->name)]);
        }

        return $this->commonStore($request, Tag::class);
    }

    public function show(int $id): JsonResponse
    {
        $tag = Tag::with('places')->findOrFail($id);

        return $this->jsonResponseOk($tag);
    }

    public function update(Request $request, Tag $tag): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:tags,slug,' . $tag->id,
            'color' => 'nullable|string|max:50',
            'description' => 'nullable|string',
        ]);

        if ($request->has('name') && !$request->filled('slug')) {
            $request->merge(['slug' => Str::slug($request->name)]);
        }

        return $this->commonUpdate($request, $tag);
    }

    public function destroy(Tag $tag): JsonResponse
    {
        return $this->commonDestroy($tag);
    }
}
