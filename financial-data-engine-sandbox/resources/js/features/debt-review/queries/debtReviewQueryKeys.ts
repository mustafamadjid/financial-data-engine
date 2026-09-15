import type { DebtListParams } from '../types/debtReview';

export const debtReviewQueryKeys = {
    all: ['debt-review'] as const,
    list: (filingId: string, params: DebtListParams) => ['debt-review', 'records', filingId, params] as const,
    coverage: (filingId: string, ruleVersion: string | null) => ['debt-review', 'coverage', filingId, ruleVersion] as const,
    detail: (filingId: string, debtRecordId: string) => ['debt-review', 'record', filingId, debtRecordId] as const,
};
