import { computed, type Ref } from 'vue';
import { useQuery } from '@tanstack/vue-query';

import { fetchCanonicalOptions, fetchMappingHistory, fetchMappingImpact, fetchMappingInventory } from '../api/conceptMappingService';
import { conceptMappingQueryKeys } from './conceptMappingQueryKeys';
import type { MappingListParams } from '../types/conceptMapping';

export function useConceptMappingInventoryQuery(params: Ref<MappingListParams>) {
    return useQuery({
        queryKey: computed(() => conceptMappingQueryKeys.list(params.value)),
        queryFn: () => fetchMappingInventory(params.value),
        staleTime: 15_000,
    });
}

export function useConceptMappingHistoryQuery(seriesKey: Ref<string | null>) {
    return useQuery({
        queryKey: computed(() => conceptMappingQueryKeys.history(seriesKey.value ?? '', { page: 1, perPage: 25 })),
        queryFn: () => fetchMappingHistory(seriesKey.value ?? ''),
        enabled: computed(() => seriesKey.value !== null),
        staleTime: 30_000,
    });
}

export function useConceptMappingImpactQuery(seriesKey: Ref<string | null>, version: Ref<number | null>) {
    return useQuery({
        queryKey: computed(() => conceptMappingQueryKeys.impact(seriesKey.value ?? '', version.value)),
        queryFn: () => fetchMappingImpact(seriesKey.value ?? '', version.value),
        enabled: computed(() => seriesKey.value !== null),
        staleTime: 15_000,
    });
}

export function useCanonicalOptionsQuery() {
    return useQuery({
        queryKey: conceptMappingQueryKeys.canonicalOptions(),
        queryFn: () => fetchCanonicalOptions(),
        staleTime: 60_000,
    });
}
