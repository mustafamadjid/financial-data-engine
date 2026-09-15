import { computed, type Ref } from 'vue';
import { useQuery } from '@tanstack/vue-query';

import { fetchValidationDetail, fetchValidationResults, fetchValidationSummary } from '../api/dataQualityService';
import { dataQualityQueryKeys } from './dataQualityQueryKeys';
import type { ValidationListParams } from '../types/dataQuality';

export function useValidationResultsQuery(filingId: Ref<string>, datasetVersion: Ref<string>, ruleSetVersion: Ref<string>, params: Ref<ValidationListParams>) {
    return useQuery({
        queryKey: computed(() => dataQualityQueryKeys.list(filingId.value, datasetVersion.value, ruleSetVersion.value, params.value)),
        queryFn: () => fetchValidationResults(filingId.value, datasetVersion.value, ruleSetVersion.value, params.value),
        enabled: computed(() => filingId.value !== '' && datasetVersion.value !== '' && ruleSetVersion.value !== ''),
        staleTime: 15_000,
    });
}

export function useValidationSummaryQuery(filingId: Ref<string>, datasetVersion: Ref<string>, ruleSetVersion: Ref<string>) {
    return useQuery({
        queryKey: computed(() => dataQualityQueryKeys.summary(filingId.value, datasetVersion.value, ruleSetVersion.value)),
        queryFn: () => fetchValidationSummary(filingId.value, datasetVersion.value, ruleSetVersion.value),
        enabled: computed(() => filingId.value !== '' && datasetVersion.value !== '' && ruleSetVersion.value !== ''),
        staleTime: 15_000,
    });
}

export function useValidationDetailQuery(validationResultId: Ref<string | null>, filingId: Ref<string>, datasetVersion: Ref<string>, ruleSetVersion: Ref<string>) {
    return useQuery({
        queryKey: computed(() => dataQualityQueryKeys.detail(filingId.value, datasetVersion.value, ruleSetVersion.value, validationResultId.value ?? '')),
        queryFn: () => fetchValidationDetail(validationResultId.value ?? '', filingId.value, datasetVersion.value, ruleSetVersion.value),
        enabled: computed(() => validationResultId.value !== null && filingId.value !== '' && datasetVersion.value !== '' && ruleSetVersion.value !== ''),
        staleTime: 30_000,
    });
}
