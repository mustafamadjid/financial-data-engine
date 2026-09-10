import { useMutation, useQueryClient } from '@tanstack/vue-query';
import { reprocessPipelineFiling, retryPipelineStage } from '../api/pipelineService';
import { pipelineQueryKeys } from '../queries/pipelineQueryKeys';

export function usePipelineActions() {
    const queryClient = useQueryClient();
    const retry = useMutation({
        mutationFn: ({ jobRunId, reason }: { jobRunId: number; reason?: string }) => retryPipelineStage(jobRunId, reason),
        retry: false,
        onSuccess: async () => {
            await Promise.all([
                queryClient.invalidateQueries({ queryKey: pipelineQueryKeys.all }),
            ]);
        },
    });
    const reprocess = useMutation({
        mutationFn: ({ filingId, stage, reason }: { filingId: string; stage: string; reason: string }) => reprocessPipelineFiling(filingId, stage, reason),
        retry: false,
        onSuccess: async () => { await queryClient.invalidateQueries({ queryKey: pipelineQueryKeys.all }); },
    });

    return { retry, reprocess };
}
