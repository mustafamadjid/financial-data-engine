<?php

namespace App\Http\Controllers\Ops;

use App\Domain\FinancialData\Pipeline\ReprocessStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ops\ReprocessPipelineRequest;
use App\Models\Filing;
use App\Services\Pipeline\ReprocessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class PipelineReprocessController extends Controller
{
    public function __invoke(Filing $filing, ReprocessPipelineRequest $request, ReprocessService $service): JsonResponse
    {
        Gate::authorize('reprocess', $filing);
        $stage = ReprocessStage::from((string) $request->validated('stage'));

        try {
            $run = $service->start($filing->filing_id, $stage, (string) $request->validated('reason'), (string) $request->user()->getAuthIdentifier());
        } catch (InvalidArgumentException $exception) {
            $message = $exception->getMessage();
            $code = str_contains($message, 'already active') ? 'ACTIVE_OPERATION' : (str_contains($message, 'not found') ? 'NOT_FOUND' : 'PREREQUISITE_MISSING');
            $status = $code === 'NOT_FOUND' ? 404 : ($code === 'ACTIVE_OPERATION' ? 409 : 422);

            return response()->json(['code' => $code, 'message' => $message], $status);
        }

        return response()->json(['data' => [
            'operation' => 'reprocess', 'filingId' => $filing->filing_id, 'pipelineRunId' => $run->id,
            'correlationId' => $run->correlation_id, 'stage' => $stage->value,
        ]], 202);
    }
}
