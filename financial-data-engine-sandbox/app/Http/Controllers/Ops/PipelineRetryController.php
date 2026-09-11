<?php

namespace App\Http\Controllers\Ops;

use App\Application\Ops\Pipeline\Exceptions\RetryPipelineException;
use App\Application\Ops\Pipeline\RetryFailedPipelineStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ops\RetryPipelineRequest;
use App\Models\PipelineJobRun;
use Illuminate\Http\JsonResponse;

final class PipelineRetryController extends Controller
{
    public function __invoke(PipelineJobRun $jobRun, RetryPipelineRequest $request, RetryFailedPipelineStage $retry): JsonResponse
    {
        try {
            $result = $retry->execute($jobRun, 'system', $request->validated('reason'));
        } catch (RetryPipelineException $exception) {
            return response()->json(['code' => $exception->errorCode, 'message' => $exception->getMessage()], $exception->status);
        }

        $newAttempt = $result['jobRun'];

        return response()->json(['data' => [
            'operation' => 'retry', 'filingId' => $newAttempt->filing_id,
            'pipelineRunId' => $newAttempt->pipeline_run_id, 'correlationId' => $newAttempt->correlation_id,
            'stage' => $newAttempt->stage, 'attempt' => $newAttempt->attempt, 'jobRunId' => $newAttempt->id,
        ]], 202);
    }
}
