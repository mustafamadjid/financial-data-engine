<?php

namespace App\Http\Controllers\Ops;

use App\Application\Ops\Pipeline\PipelineFilingQuery;
use App\Application\Ops\Pipeline\PipelineSummaryQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ops\PipelineListRequest;
use App\Http\Resources\Ops\PipelineFilingResource;
use Illuminate\Http\JsonResponse;

final class PipelineDataController extends Controller
{
    public function index(PipelineListRequest $request, PipelineFilingQuery $filingQuery): JsonResponse
    {
        $paginator = $filingQuery->paginate($request->listFilters());

        return response()->json([
            'data' => PipelineFilingResource::collection($paginator->getCollection())->resolve(),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'lastPage' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function summary(PipelineSummaryQuery $summaryQuery): JsonResponse
    {
        return response()->json(['data' => $summaryQuery->summary()]);
    }
}
