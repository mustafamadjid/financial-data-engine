import type { PipelineListParams } from '../types/pipeline';

export const pipelineQueryKeys = {
    all: ['pipeline'] as const,
    list: (params: PipelineListParams) => ['pipeline', 'filings', params] as const,
    summary: () => ['pipeline', 'summary'] as const,
    detail: (filingId: string) => ['pipeline', 'filing', filingId] as const,
    history: (filingId: string, params: { page: number; perPage: number }) => ['pipeline', 'filing', filingId, 'history', params] as const,
};
