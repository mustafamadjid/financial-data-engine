<?php

namespace App\Http\Controllers\Ops;

use App\Application\Ops\ConceptMapping\ReprocessAffectedFilings;
use App\Application\Ops\ConceptMapping\ReprocessAffectedFilingsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ops\ReprocessAffectedFilingsRequest;
use Illuminate\Http\JsonResponse;

final class ReprocessAffectedFilingsController extends Controller
{
    public function __invoke(string $mappingSeries, ReprocessAffectedFilingsRequest $request, ReprocessAffectedFilings $service): JsonResponse
    {
        try {
            $data = $service->execute(
                $mappingSeries,
                array_values(array_map('strval', $request->validated('filing_ids'))),
                (int) $request->validated('expected_mapping_set_version'),
                (string) $request->validated('reason'),
            );
        } catch (ReprocessAffectedFilingsException $exception) {
            return response()->json(['code' => $exception->errorCode, 'message' => $exception->getMessage()], $exception->status);
        }

        return response()->json(['data' => $data], 202);
    }
}
