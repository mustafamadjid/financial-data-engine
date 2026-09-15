import { useMutation, useQueryClient } from '@tanstack/vue-query';

import { createMappingVersion } from '../api/conceptMappingService';
import { conceptMappingQueryKeys } from './conceptMappingQueryKeys';
import type { CreateMappingVersionPayload } from '../types/conceptMapping';

export function useCreateMappingVersion(mappingSeriesKey: () => string | null) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (payload: CreateMappingVersionPayload) => {
            const seriesKey = mappingSeriesKey();
            if (seriesKey === null) throw new Error('Select a mapping series before saving a version.');
            return createMappingVersion(seriesKey, payload);
        },
        retry: false,
        onSuccess: async () => {
            const seriesKey = mappingSeriesKey();
            await Promise.all([
                queryClient.invalidateQueries({ queryKey: conceptMappingQueryKeys.lists() }),
                ...(seriesKey === null ? [] : [
                    queryClient.invalidateQueries({ queryKey: ['concept-mapping', 'series', seriesKey] }),
                ]),
            ]);
        },
    });
}
