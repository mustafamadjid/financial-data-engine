import { isRecord, requestOps } from '../../ops/api/opsHttpClient';
import type { MarkReviewItemPayload, ReviewItemResponse, ReviewEntityType } from '../types/reviewItem';

export async function markReviewItem(payload: MarkReviewItemPayload): Promise<ReviewItemResponse> {
    const response = await requestOps<unknown>('/ops/actions/review-items', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            entity_type: payload.entityType,
            entity_id: payload.entityId,
            filing_id: payload.filingId,
            expected_version: payload.expectedVersion,
            rationale: payload.rationale,
            idempotency_key: payload.idempotencyKey,
        }),
    });

    if (!isRecord(response) || !isRecord(response.data)) {
        throw new Error('The review item response is malformed.');
    }

    return decodeReviewItem(response.data);
}

function decodeReviewItem(value: unknown): ReviewItemResponse {
    if (
        !isRecord(value)
        || typeof value.reviewItemId !== 'string'
        || !isReviewEntityType(value.entityType)
        || typeof value.entityId !== 'string'
        || typeof value.filingId !== 'string'
        || !isReviewStatus(value.status)
        || typeof value.rationale !== 'string'
        || typeof value.expectedVersion !== 'string'
        || typeof value.createdBy !== 'string'
        || (value.createdAt !== null && typeof value.createdAt !== 'string')
        || typeof value.active !== 'boolean'
    ) {
        throw new Error('A review item response is malformed.');
    }

    return value as unknown as ReviewItemResponse;
}

function isReviewEntityType(value: unknown): value is ReviewEntityType {
    return value === 'normalized_fact' || value === 'validation_result';
}

function isReviewStatus(value: unknown): value is ReviewItemResponse['status'] {
    return value === 'OPEN' || value === 'RESOLVED' || value === 'REOPENED';
}
