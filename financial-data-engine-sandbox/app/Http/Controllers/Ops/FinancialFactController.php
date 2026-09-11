<?php

namespace App\Http\Controllers\Ops;

use App\Application\Ops\FinancialReview\FinancialFactQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ops\FinancialFactListRequest;
use App\Models\NormalizedFact;
use Illuminate\Http\JsonResponse;

final class FinancialFactController extends Controller
{
    public function index(FinancialFactListRequest $request, FinancialFactQuery $query): JsonResponse
    {
        $paginator = $query->paginate($request->filters());

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (NormalizedFact $fact): array => $query->item($fact))->values()->all(),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'lastPage' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(NormalizedFact $normalizedFact, FinancialFactQuery $query): JsonResponse
    {
        return response()->json(['data' => $query->detail($normalizedFact)]);
    }
}
