import { useQuery } from '@tanstack/vue-query';
import { computed, type Ref } from 'vue';

import { fetchPipelineFilingDetail, fetchPipelineFilingHistory } from '../api/pipelineService';
import { pipelineQueryKeys } from '../queries/pipelineQueryKeys';
import type { PipelineHistoryParams } from '../types/pipeline';

export function usePipelineDetailQuery(filingId: Ref<string | null>) {
    return useQuery({
        queryKey: computed(() => pipelineQueryKeys.detail(filingId.value ?? '')),
        queryFn: () => fetchPipelineFilingDetail(filingId.value ?? ''),
        enabled: computed(() => filingId.value !== null),
    });
}

export function usePipelineHistoryQuery(filingId: Ref<string | null>, params: Ref<PipelineHistoryParams>) {
    return useQuery({
        queryKey: computed(() => pipelineQueryKeys.history(filingId.value ?? '', params.value)),
        queryFn: () => fetchPipelineFilingHistory(filingId.value ?? '', params.value),
        enabled: computed(() => filingId.value !== null),
        placeholderData: (previousData) => previousData,
    });
}
