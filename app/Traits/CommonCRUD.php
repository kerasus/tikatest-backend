<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait CommonCRUD
{
    /**
     * Display a listing of the resource.
     *
     * @return array|JsonResponse
     */
    public function commonIndex(Request $request, $modelClass, array $config = [])
    {
        // load variables
        $modelQuery = $modelClass::query();
        $configArray = $this->getConfigArray($config);
        $perPage = ($request->has('length')) ? $request->get('length') : 10;

        $this->buildFilterQuery($request, $modelQuery, $modelClass, $configArray);

        // load appends
        $attachedCollection = null;
        $setAppends = $configArray['setAppends'];
        if ($configArray['returnModelQuery']) {
            return $this->getModelQueryWithAttachedCollectionClosure($modelQuery, $perPage, $setAppends);
        } elseif (count($configArray['setAppends']) > 0) {
            $attachedCollection = $this->getAttachedCollection($modelQuery, $setAppends, $perPage);
            //                $modelQuery->paginate($perPage)
            //                    ->getCollection()->map(function ($item) use ($setAppends) {
            //                    return $item->setAppends($setAppends);
            //                });
        }

        // return json response
        if (isset($attachedCollection)) {
            return $this->jsonResponseOk($modelQuery->paginate($perPage)->setCollection($attachedCollection));
        }

        return $this->jsonResponseOk($modelQuery->paginate($perPage));
    }

    private function buildFilterQuery($request, &$modelQuery, $modelClass, $configArray)
    {
        $this->sorting($request, $modelQuery);
        $this->select($configArray['select'], $modelQuery, $modelClass);
        $this->loadScopes($request, $modelQuery, $configArray['scopes']);
        $this->filterByDate($request, $modelQuery, $configArray['filterDate']);
        $this->filterByKeys($request, $modelQuery, $configArray['filterKeys']);
        $this->filterByMultipleColumnKeys($request, $modelQuery, $configArray['filterOnMultipleColumnKeys']);
        $this->filterOrByKeys($request, $modelQuery, $configArray['filterOrKeys']);
        $this->filterByKeysExact($request, $modelQuery, $configArray['filterKeysExact']);
        $this->filterOrByKeysExact($request, $modelQuery, $configArray['filterOrKeysExact']);
        $this->filterByKeysIn($request, $modelQuery, $configArray['filterKeysIn']); // {key}_in in request
        $this->filterByRelationKeys($request, $modelQuery, $configArray['filterRelationKeys']);
        $this->filterByRelationIds($request, $modelQuery, $configArray['filterRelationIds']);
        $modelQuery->with($configArray['eagerLoads']);
    }

    private function getConfigArray($config)
    {
        $configArray = [
            'select' => $this->getDefault($config, 'select', []),
            'scopes' => $this->getDefault($config, 'scopes', []),
            'eagerLoads' => $this->getDefault($config, 'eagerLoads', []),
            'filterDate' => $this->getDefault($config, 'filterDate', []),
            'filterKeys' => $this->getDefault($config, 'filterKeys', []),
            'filterOnMultipleColumnKeys' => $this->getDefault($config, 'filterOnMultipleColumnKeys', []),
            'filterOrKeys' => $this->getDefault($config, 'filterOrKeys', []),
            'filterKeysIn' => $this->getDefault($config, 'filterKeysIn', []),
            'filterKeysExact' => $this->getDefault($config, 'filterKeysExact', []),
            'filterOrKeysExact' => $this->getDefault($config, 'filterOrKeysExact', []),
            'setAppends' => $this->getDefault($config, 'setAppends', []),
            'returnModelQuery' => $this->getDefault($config, 'returnModelQuery', []),
            'filterRelationIds' => $this->getDefault($config, 'filterRelationIds', []),
            'filterRelationKeys' => $this->getDefault($config, 'filterRelationKeys', []),
        ];

        return $configArray;
    }

    private function getAttachedCollection($updatedModelQuery, $setAppends, $perPage)
    {
        return $updatedModelQuery->paginate($perPage)
            ->getCollection()->map(function ($item) use ($setAppends) {
                return $item->setAppends($setAppends);
            });
    }

    private function getModelQueryWithAttachedCollectionClosure($modelQuery, $perPage, $setAppends)
    {
        $responseWithAttachedCollection = function ($updatedModelQuery) use ($perPage, $setAppends) {
            $attachedCollection = $this->getAttachedCollection($updatedModelQuery, $setAppends, $perPage);

            return $this->jsonResponseOk(
                $updatedModelQuery->paginate($perPage)
                    ->setCollection($attachedCollection)
            );
        };

        return [
            'responseWithAttachedCollection' => $responseWithAttachedCollection,
            'modelQuery' => $modelQuery,
        ];
    }

    private function loadScopes(Request $request, &$modelQuery, $scopes)
    {
        $model = $modelQuery->getModel();

        foreach ($scopes as $item) {
            if (! $request->has($item)) {
                continue;
            }

            $scopeValue = $request->get($item);

            // اگر مقدار خالی، null یا false بود رد شو
            if ($scopeValue === false || $scopeValue === 'false' || $scopeValue === null || $scopeValue === '') {
                continue;
            }

            $scopeMethod = 'scope' . ucfirst($item);

            // بررسی می‌کنیم آیا متد اسکوپ اصلاً آرگومان مقداری قبول می‌کنه یا نه
            if (method_exists($model, $scopeMethod)) {
                $reflection = new \ReflectionMethod($model, $scopeMethod);
                $numberOfParameters = $reflection->getNumberOfParameters();

                // پارامتر اول همیشه $query است؛ اگر بیشتر از 1 پارامتر داشت، مقدار $scopeValue را پاس می‌دهیم
                if ($numberOfParameters > 1) {
                    $modelQuery->$item($scopeValue);
                } else {
                    // اسکوپ‌های بدون آرگومان (فقط به شرط true/1 فعال می‌شوند)
                    if ($scopeValue === true || $scopeValue === 'true' || $scopeValue == 1) {
                        $modelQuery->$item();
                    }
                }
            } else {
                // فال‌بک برای حالتی که اسکوپ دینامیک باشه
                $modelQuery->$item($scopeValue);
            }
        }
    }

    private function filterByKeys(Request $request, &$modelQuery, $filterKeys)
    {
        foreach ($filterKeys as $item) {
            $this->filterByKey($request, $item, $modelQuery);
        }
    }

    private function filterByMultipleColumnKeys(Request $request, &$modelQuery, $filterKeys)
    {
        foreach ($filterKeys as $item) {
            $this->filterByMultipleColumnKey($request, $item, $modelQuery);
        }
    }

    private function filterOrByKeys(Request $request, &$modelQuery, $filterOrKeys)
    {
        foreach ($filterOrKeys as $item) {
            $this->filterOrByKey($request, $item, $modelQuery);
        }
    }

    private function filterByKeysExact(Request $request, &$modelQuery, $filterKeys)
    {
        foreach ($filterKeys as $item) {
            $this->filterByKeyExact($request, $item, $modelQuery);
        }
    }

    private function filterOrByKeysExact(Request $request, &$modelQuery, $filterOrKeys)
    {
        if (empty($filterOrKeys)) {
            return;
        }

        $modelQuery->where(function ($query) use ($request, $filterOrKeys) {
            foreach ($filterOrKeys as $item) {
                $this->filterOrByKeyExact($request, $item, $query);
            }
        });
    }

    private function filterByKeysIn(Request $request, &$modelQuery, $filterKeysIn)
    {
        foreach ($filterKeysIn as $item) {
            $this->filterByKeyIn($request, $item, $modelQuery);
        }
    }

    private function filterByRelationKeys(Request $request, &$modelQuery, $filterRelationKeys)
    {
        foreach ($filterRelationKeys as $item) {
            $this->filterByRelationKey($request, $item, $modelQuery);
        }
    }

    private function filterByRelationIds(Request $request, &$modelQuery, $filterRelationIds)
    {
        foreach ($filterRelationIds as $item) {
            $this->filterByRelationId($request, $item, $modelQuery);
        }
    }

    private function select(array $select, &$modelQuery, $modelClass)
    {
        $tableName = (new $modelClass)->getTable();
        foreach ($select as $item) {
            if (! strpos($item, '.')) {
                $item = $tableName.'.'.$item;
            }
            $modelQuery->addSelect($item);
        }
    }

    private function sorting(Request $request, &$modelQuery)
    {
        $sortation_field = $request->get('sortation_field');
        $sortation_order = $request->get('sortation_order');

        if (! isset($sortation_field) || ! isset($sortation_order)) {
            return;
        }

        if (! strpos($sortation_field, '.')) {
            $modelQuery->orderBy($sortation_field, strtoupper($sortation_order));
        } else {
            $modelQuery->orderByPowerJoins($sortation_field, strtoupper($sortation_order));
        }
    }

    private function getDefault(array $config, $key, $default)
    {
        return isset($config[$key]) ? $config[$key] : $default;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return JsonResponse
     */
    public function commonStore(Request $request, $modelClass)
    {
        $createdModel = $modelClass::create($request->all());

        return $this->show($request, $createdModel->id);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return JsonResponse
     */
    public function commonUpdate(Request $request, $model)
    {
        $model->fill($request->all());

        if ($model->save()) {
            return $this->show($request, $model->id);
        } else {
            return $this->jsonResponseServerError([
                'errors' => [
                    'commonUpdate' => [
                        'مشکلی در ویرایش اطلاعات رخ داده است.',
                    ],
                ],
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return JsonResponse
     */
    public function commonDestroy($model)
    {
        \Log::info('commonDestroy', [
            '$model' => $model
        ]);
        try {
            $deleted = $model->delete();

            if ($deleted === false) {
                \Log::warning('Model deletion was cancelled', [
                    'model' => $model::class,
                    'id' => $model->getKey(),
                    'exists' => $model->exists,
                    'was_recently_created' => $model->wasRecentlyCreated,
                    'attributes' => $model->getAttributes(),
                ]);

                return response()->json([
                    'message' => 'عملیات حذف توسط رویداد یا منطق مدل لغو شد.',
                    'errors' => [
                        'delete' => [
                            'یکی از رویدادهای deleting یا Observerهای مدل، عملیات حذف را لغو کرده است.',
                        ],
                    ],
                ], 409);
            }

            return $this->jsonResponseOk([
                'message' => 'حذف با موفقیت انجام شد.',
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            \Log::error('Delete QueryException', [
                'model' => $model::class,
                'id' => $model->getKey(),
                'message' => $e->getMessage(),
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings(),
            ]);

            return response()->json([
                'message' => 'خطای دیتابیس در حذف',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        } catch (\Throwable $e) {
            \Log::error('Delete Throwable', [
                'model' => $model::class,
                'id' => $model->getKey(),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'خطای غیرمنتظره در حذف',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function getHasRelations($modelClass, $relations)
    {
        $hasRelations = [];
        foreach ($relations as $relation) {
            if (! $modelClass->$relation()->exists()) {
                $hasRelations[] = $relation;
            }
        }

        return $hasRelations;
    }
}
