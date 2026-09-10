import { keepPreviousData, useQuery } from '@tanstack/vue-query';
import { computed, type ComputedRef, type Ref } from 'vue';

import { fetchPipelineFilings, fetchPipelineSummary, OpsHttpError } from '../api/pipelineService';
import { pipelineQueryKeys } from '../queries/pipelineQueryKeys';
import type { PaginatedResponse, PipelineFilingListItem, PipelineListParams } from '../types/pipeline';

const ACTIVE_POLL_INTERVAL_MS = 10_000;
const IDLE_POLL_INTERVAL_MS = 60_000;

interface PollingStage {
    status: string;
}

interface PollingRow {
    stages: Record<string, PollingStage>;
}

interface PollingData {
    data: PollingRow[];
}

export function usePipelineListQuery(params: ComputedRef<PipelineListParams>) {
    return useQuery({
        queryKey: computed(() => pipelineQueryKeys.list(params.value)),
        queryFn: () => fetchPipelineFilings(params.value),
        staleTime: 5_000,
        placeholderData: keepPreviousData,
        retry: shouldRetryRead,
        retryDelay: (attempt) => Math.min(500 * 2 ** attempt, 4_000),
        refetchInterval: (query) => getPipelinePollingInterval(query.state.data, isDocumentVisible()),
        refetchIntervalInBackground: false,
    });
}

export function usePipelineSummaryQuery(hasActiveWork: Ref<boolean>) {
    return useQuery({
        queryKey: pipelineQueryKeys.summary(),
        queryFn: fetchPipelineSummary,
        staleTime: 10_000,
        retry: shouldRetryRead,
        retryDelay: (attempt) => Math.min(500 * 2 ** attempt, 4_000),
        refetchInterval: () => (isDocumentVisible() ? (hasActiveWork.value ? ACTIVE_POLL_INTERVAL_MS : IDLE_POLL_INTERVAL_MS) : false),
        refetchIntervalInBackground: false,
    });
}

export function usePipelineOverviewQueries(params: ComputedRef<PipelineListParams>) {
    const list = usePipelineListQuery(params);
    const hasActiveWork = computed(() => hasActivePipelineWork(list.data.value));
    const summary = usePipelineSummaryQuery(hasActiveWork);

    return { list, summary, hasActiveWork };
}

export function getPipelinePollingInterval(data: PollingData | undefined, isVisible = true): number | false {
    if (!isVisible) {
        return false;
    }

    return hasActivePipelineWork(data) ? ACTIVE_POLL_INTERVAL_MS : IDLE_POLL_INTERVAL_MS;
}

function hasActivePipelineWork(data: PollingData | undefined): boolean {
    return data?.data.some((row) => Object.values(row.stages).some((stage) => stage.status === 'QUEUED' || stage.status === 'RUNNING')) ?? false;
}

function shouldRetryRead(failureCount: number, error: Error): boolean {
    if (error instanceof OpsHttpError && error.status >= 400 && error.status < 500) {
        return false;
    }

    return failureCount < 2;
}

function isDocumentVisible(): boolean {
    return typeof document === 'undefined' || document.visibilityState === 'visible';
}

export type PipelineListData = PaginatedResponse<PipelineFilingListItem>;
