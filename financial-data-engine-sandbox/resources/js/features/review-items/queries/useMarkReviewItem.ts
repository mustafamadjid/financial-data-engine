import { useMutation, useQueryClient } from '@tanstack/vue-query';

import { dataQualityQueryKeys } from '../../data-quality/queries/dataQualityQueryKeys';
import { financialReviewQueryKeys } from '../../financial-review/queries/financialReviewQueryKeys';
import { markReviewItem } from '../api/reviewItemService';
import { reviewItemQueryKeys } from './reviewItemQueryKeys';
import type { MarkReviewItemPayload } from '../types/reviewItem';

export function useMarkReviewItem() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (payload: MarkReviewItemPayload) => markReviewItem(payload),
        retry: false,
        onSuccess: async (_item, payload) => {
            await queryClient.invalidateQueries({ queryKey: reviewItemQueryKeys.entity(payload.entityType, payload.entityId) });
            await queryClient.invalidateQueries({
                queryKey: payload.entityType === 'normalized_fact'
                    ? financialReviewQueryKeys.all
                    : dataQualityQueryKeys.all,
            });
        },
    });
}
