import { computed, ref, watch, type ComputedRef, type Ref } from 'vue';

import { useDebouncedValue } from '../../ops/composables/useDebouncedValue';
import type { PipelineListParams, PipelinePerPage, PipelineProcessingStage, PipelineSort, QualityStatus } from '../types/pipeline';

const DEFAULT_SORT: PipelineSort = 'last_processed_desc';
const DEFAULT_PER_PAGE: PipelinePerPage = 25;
const SEARCH_DEBOUNCE_MS = 300;

interface PipelineFiltersOptions {
    search?: string;
    onUrlChange?: (query: string) => void;
}

interface PipelineFilters {
    searchInput: Ref<string>;
    processingStage: Ref<PipelineProcessingStage | undefined>;
    qualityStatus: Ref<QualityStatus | undefined>;
    period: Ref<string | undefined>;
    sort: Ref<PipelineSort>;
    page: Ref<number>;
    perPage: Ref<PipelinePerPage>;
    params: ComputedRef<PipelineListParams>;
    setPage: (page: number) => void;
}

export function usePipelineFilters(options: PipelineFiltersOptions = {}): PipelineFilters {
    const initial = new URLSearchParams(options.search ?? '');
    const search = useDebouncedValue(initial.get('search') ?? '', SEARCH_DEBOUNCE_MS);
    const searchInput = search.input;
    const processingStage = ref<PipelineProcessingStage | undefined>(parseProcessingStage(initial.get('processing_stage')));
    const qualityStatus = ref<QualityStatus | undefined>(parseQualityStatus(initial.get('quality_status')));
    const period = ref(stringOrUndefined(initial.get('period')));
    const sort = ref<PipelineSort>(parseSort(initial.get('sort')));
    const page = ref(parsePositiveInteger(initial.get('page')) ?? 1);
    const perPage = ref<PipelinePerPage>(parsePerPage(initial.get('per_page')));

    const params = computed<PipelineListParams>(() => ({
        search: stringOrUndefined(search.value),
        processingStage: processingStage.value,
        qualityStatus: qualityStatus.value,
        period: stringOrUndefined(period.value),
        sort: sort.value,
        page: page.value,
        perPage: perPage.value,
    }));

    watch(() => search.value, () => { page.value = 1; });

    watch([processingStage, qualityStatus, period, sort, perPage], () => {
        page.value = 1;
    });

    watch(
        params,
        (next) => {
            options.onUrlChange?.(serializePipelineFilters(next));
        },
        { deep: false },
    );

    return {
        searchInput,
        processingStage,
        qualityStatus,
        period,
        sort,
        page,
        perPage,
        params,
        setPage: (nextPage) => {
            page.value = Math.max(1, Math.floor(nextPage));
        },
    };
}

export function serializePipelineFilters(params: PipelineListParams): string {
    const query = new URLSearchParams();
    appendString(query, 'search', params.search);
    appendString(query, 'processing_stage', params.processingStage);
    appendString(query, 'quality_status', params.qualityStatus);
    appendString(query, 'period', params.period);
    query.set('sort', params.sort);
    query.set('page', String(params.page));
    query.set('per_page', String(params.perPage));
    return `?${query.toString()}`;
}

function appendString(query: URLSearchParams, key: string, value: string | undefined): void {
    const normalized = stringOrUndefined(value);
    if (normalized !== undefined) {
        query.set(key, normalized);
    }
}

function stringOrUndefined(value: string | null | undefined): string | undefined {
    const normalized = value?.trim();
    return normalized === undefined || normalized === '' ? undefined : normalized;
}

function parsePositiveInteger(value: string | null): number | undefined {
    if (value === null || !/^\d+$/.test(value)) {
        return undefined;
    }
    const parsed = Number(value);
    return Number.isSafeInteger(parsed) && parsed >= 1 ? parsed : undefined;
}

function parsePerPage(value: string | null): PipelinePerPage {
    return value === '50' ? 50 : value === '100' ? 100 : DEFAULT_PER_PAGE;
}

function parseSort(value: string | null): PipelineSort {
    return value === 'last_processed_asc' || value === 'issuer_asc' ? value : DEFAULT_SORT;
}

function parseQualityStatus(value: string | null): QualityStatus | undefined {
    return value === 'PENDING' || value === 'VERIFIED' || value === 'REVIEW_REQUIRED' || value === 'FAILED' ? value : undefined;
}

function parseProcessingStage(value: string | null): PipelineProcessingStage | undefined {
    const stages: readonly PipelineProcessingStage[] = [
        'DISCOVERED',
        'DOWNLOADING',
        'DOWNLOADED',
        'PARSING',
        'PARSED',
        'NORMALIZING',
        'NORMALIZED',
        'VALIDATING',
        'VALIDATED',
        'PUBLISHING',
        'PUBLISHED',
        'FAILED',
    ];
    return stages.includes(value as PipelineProcessingStage) ? (value as PipelineProcessingStage) : undefined;
}
