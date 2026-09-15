<?php

namespace App\Http\Controllers\Ops;

use App\Application\Ops\DataQuality\ValidationQualityQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ops\ValidationResultListRequest;
use Illuminate\Http\JsonResponse;

final class DataQualityController extends Controller
{
    public function index(ValidationResultListRequest $request, ValidationQualityQuery $query): JsonResponse
    {
        $paginator = $query->paginate($request->filters());

        return response()->json([
            'data' => $paginator->getCollection()->values()->all(),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'lastPage' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function summary(ValidationResultListRequest $request, ValidationQualityQuery $query): JsonResponse
    {
        return response()->json(['data' => $query->summary($request->filters())]);
    }

    public function show(string $validationResult, ValidationResultListRequest $request, ValidationQualityQuery $query): JsonResponse
    {
        $detail = $query->detail($validationResult, $request->filters());
        if ($detail === null) {
            return response()->json([
                'code' => 'VALIDATION_EXECUTION_NOT_FOUND',
                'message' => 'The validation result is not part of the selected execution.',
            ], 404);
        }

        return response()->json(['data' => $detail]);
    }
}
