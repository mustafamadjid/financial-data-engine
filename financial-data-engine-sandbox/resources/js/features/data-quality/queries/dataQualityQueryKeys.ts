import type { ValidationListParams } from '../types/dataQuality';

export const dataQualityQueryKeys = {
    all: ['data-quality'] as const,
    list: (filingId: string, datasetVersion: string, ruleSetVersion: string, params: ValidationListParams) => ['data-quality', 'results', filingId, datasetVersion, ruleSetVersion, params] as const,
    summary: (filingId: string, datasetVersion: string, ruleSetVersion: string) => ['data-quality', 'summary', filingId, datasetVersion, ruleSetVersion] as const,
    detail: (filingId: string, validationResultId: string) => ['data-quality', 'result', filingId, validationResultId] as const,
};
