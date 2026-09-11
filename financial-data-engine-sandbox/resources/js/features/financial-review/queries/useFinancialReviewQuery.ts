import { computed, type Ref } from 'vue';
import { useQuery } from '@tanstack/vue-query';

import { fetchFinancialFactDetail, fetchFinancialFacts } from '../api/financialReviewService';
import { financialReviewQueryKeys } from './financialReviewQueryKeys';
import type { FinancialFactListParams } from '../types/financialReview';

export function useFinancialFactsQuery(params: Ref<FinancialFactListParams>) {
    return useQuery({
        queryKey: computed(() => financialReviewQueryKeys.list(params.value.filingId ?? '', params.value)),
        queryFn: () => fetchFinancialFacts(params.value.filingId, params.value),
        staleTime: 15_000,
    });
}

export function useFinancialFactDetailQuery(normalizedFactId: Ref<string | null>) {
    return useQuery({
        queryKey: computed(() => financialReviewQueryKeys.detail('', normalizedFactId.value ?? '')),
        queryFn: () => fetchFinancialFactDetail(normalizedFactId.value ?? ''),
        enabled: computed(() => normalizedFactId.value !== null),
        staleTime: 30_000,
    });
}
