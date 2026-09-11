import type { MappingListParams } from '../types/conceptMapping';

export const conceptMappingQueryKeys = {
    all: ['concept-mapping'] as const,
    lists: () => ['concept-mapping', 'series'] as const,
    list: (params: MappingListParams) => ['concept-mapping', 'series', params] as const,
    history: (mappingSeriesKey: string, params: { page: number; perPage: number }) => ['concept-mapping', 'series', mappingSeriesKey, 'history', params] as const,
    impact: (mappingSeriesKey: string, version: number | null) => ['concept-mapping', 'series', mappingSeriesKey, 'impact', version] as const,
};
