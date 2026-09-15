import type { FinancialFactListParams } from '../types/financialReview';

export const financialReviewQueryKeys = {
    all: ['financial-review'] as const,
    lists: () => ['financial-review', 'facts'] as const,
    list: (filingId: string, params: FinancialFactListParams) => ['financial-review', 'facts', filingId, params] as const,
    detail: (filingId: string, normalizedFactId: string) => ['financial-review', 'fact', filingId, normalizedFactId] as const,
};
