import type { ActionCapability } from '../../ops/types/ops';

export type ReviewEntityType = 'normalized_fact' | 'validation_result';

export interface ReviewItemCapability extends ActionCapability {}

export interface MarkReviewItemPayload {
    entityType: ReviewEntityType;
    entityId: string;
    filingId: string;
    expectedVersion: string;
    rationale: string;
    idempotencyKey: string;
}

export interface ReviewItemResponse {
    reviewItemId: string;
    entityType: ReviewEntityType;
    entityId: string;
    filingId: string;
    status: 'OPEN' | 'RESOLVED' | 'REOPENED';
    rationale: string;
    expectedVersion: string;
    createdBy: string;
    createdAt: string | null;
    active: boolean;
}
