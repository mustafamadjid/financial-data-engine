<?php

namespace App\Http\Controllers\Ops;

use App\Application\Ops\Review\MarkReviewItem;
use App\Application\Ops\Review\ReviewMutationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ops\MarkReviewItemRequest;
use App\Models\ReviewItem;
use Illuminate\Http\JsonResponse;

final class MarkReviewItemController extends Controller
{
    public function __invoke(MarkReviewItemRequest $request, MarkReviewItem $markReviewItem): JsonResponse
    {
        try {
            $result = $markReviewItem->execute([
                ...$request->validated(),
                'correlation_id' => $request->header('X-Correlation-ID'),
            ]);
        } catch (ReviewMutationException $exception) {
            $payload = [
                'code' => $exception->errorCode,
                'message' => $exception->getMessage(),
            ];
            if ($exception->field !== null) {
                $payload['fieldErrors'] = [$exception->field => [$exception->getMessage()]];
            }

            return response()->json($payload, $exception->status);
        }

        return response()->json(['data' => $this->dto($result['item'])], $result['created'] ? 201 : 200);
    }

    /** @return array<string, mixed> */
    private function dto(ReviewItem $item): array
    {
        return [
            'reviewItemId' => (string) $item->review_item_id,
            'entityType' => (string) $item->entity_type,
            'entityId' => (string) $item->entity_id,
            'filingId' => (string) $item->filing_id,
            'status' => (string) $item->status,
            'rationale' => $item->rationale,
            'expectedVersion' => $item->expected_version,
            'createdBy' => $item->created_by,
            'createdAt' => $item->created_at?->toIso8601String(),
            'active' => $item->active_identity !== null,
        ];
    }
}
