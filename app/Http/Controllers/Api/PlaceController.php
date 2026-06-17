<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Place;
use App\Services\PlaceImporter;
use App\Traits\CommonCRUD;
use App\Traits\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaceController extends Controller
{
    use Filter, CommonCRUD;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:places.view')->only(['index', 'show']);
        $this->middleware('permission:places.create')->only(['store']);
        $this->middleware('permission:places.update')->only(['update']);
        $this->middleware('permission:places.delete')->only(['destroy']);
        $this->middleware('permission:places.import')->only(['import']);
        $this->middleware('permission:places.manage-tags')->only(['syncTags', 'attachTags', 'detachTags']);
    }

    public function index(Request $request): JsonResponse
    {
        $config = [
            'filterKeys' => [
                'name',
                'address',
                'phone',
                'url',
                'keyword',
            ],
            'filterKeysExact' => [
                'provider',
            ],
            'filterKeysIn' => [
                'provider',
            ],
            'filterRelationIds' => [
                [
                    'requestKey' => 'tag_ids',
                    'relationName' => 'tags',
                ],
            ],
            'scopes' => [
                'provider',
                'tagged',
                'untagged',
            ],
            'eagerLoads' => ['tags'],
        ];

        return $this->commonIndex($request, Place::class, $config);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => 'required|string|in:google,balad,neshan,mapir',
            'external_id' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'url' => 'nullable|string|max:2048',
            'keyword' => 'nullable|string|max:255',
            'raw_data' => 'nullable|array',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
        ]);

        $place = Place::create($request->except('tag_ids'));

        if ($request->filled('tag_ids')) {
            $place->tags()->sync($request->input('tag_ids'));
        }

        return $this->show($place->id);
    }

    public function show(int $id): JsonResponse
    {
        $place = Place::with('tags')->findOrFail($id);

        return $this->jsonResponseOk($place);
    }

    public function update(Request $request, Place $place): JsonResponse
    {
        $request->validate([
            'provider' => 'sometimes|required|string|in:google,balad,neshan,mapir',
            'external_id' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'url' => 'nullable|string|max:2048',
            'keyword' => 'nullable|string|max:255',
            'raw_data' => 'nullable|array',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
        ]);

        $place->fill($request->except('tag_ids'));
        $place->save();

        if ($request->has('tag_ids')) {
            $place->tags()->sync($request->input('tag_ids', []));
        }

        return $this->show($place->id);
    }

    public function destroy(Place $place): JsonResponse
    {
        return $this->commonDestroy($place);
    }

    public function import(Request $request, PlaceImporter $importer): JsonResponse
    {
        $request->validate([
            'provider' => 'nullable|string|in:google,balad,neshan,mapir',
            'file' => 'nullable|string|max:255',
        ]);

        $result = $importer->import(
            $request->input('provider'),
            $request->input('file')
        );

        return $this->jsonResponseOk($result);
    }

    public function syncTags(Request $request, Place $place): JsonResponse
    {
        $request->validate([
            'tag_ids' => 'required|array',
            'tag_ids.*' => 'exists:tags,id',
        ]);

        $place->tags()->sync($request->input('tag_ids'));

        return $this->show($place->id);
    }

    public function attachTags(Request $request, Place $place): JsonResponse
    {
        $request->validate([
            'tag_ids' => 'required|array',
            'tag_ids.*' => 'exists:tags,id',
        ]);

        $place->tags()->syncWithoutDetaching($request->input('tag_ids'));

        return $this->show($place->id);
    }

    public function detachTags(Request $request, Place $place): JsonResponse
    {
        $request->validate([
            'tag_ids' => 'required|array',
            'tag_ids.*' => 'exists:tags,id',
        ]);

        $place->tags()->detach($request->input('tag_ids'));

        return $this->show($place->id);
    }
}
