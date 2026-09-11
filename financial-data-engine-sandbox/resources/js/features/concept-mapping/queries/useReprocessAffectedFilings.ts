import { useMutation, useQueryClient } from '@tanstack/vue-query';

import { reprocessAffectedFilings } from '../api/conceptMappingService';
import { conceptMappingQueryKeys } from './conceptMappingQueryKeys';
import type { ReprocessAffectedFilingsPayload } from '../types/conceptMapping';

export function useReprocessAffectedFilings(mappingSeriesKey: () => string | null) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (payload: ReprocessAffectedFilingsPayload) => {
            const seriesKey = mappingSeriesKey();
            if (seriesKey === null) throw new Error('Select a mapping series before reprocessing.');
            return reprocessAffectedFilings(seriesKey, payload);
        },
        retry: false,
        onSuccess: async () => {
            const seriesKey = mappingSeriesKey();
            await Promise.all([
                queryClient.invalidateQueries({ queryKey: conceptMappingQueryKeys.lists() }),
                queryClient.invalidateQueries({ queryKey: ['pipeline'] }),
                queryClient.invalidateQueries({ queryKey: ['financial-review'] }),
                ...(seriesKey === null ? [] : [queryClient.invalidateQueries({ queryKey: ['concept-mapping', 'series', seriesKey] })]),
            ]);
        },
    });
}
