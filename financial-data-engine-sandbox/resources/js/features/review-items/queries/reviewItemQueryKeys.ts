import type { ReviewEntityType } from '../types/reviewItem';

export const reviewItemQueryKeys = {
    all: ['review-items'] as const,
    entity: (entityType: ReviewEntityType, entityId: string) => ['review-items', entityType, entityId] as const,
};
