import { describe, expect, it } from 'vitest';

import { conceptMappingQueryKeys } from '../../../resources/js/features/concept-mapping/queries/conceptMappingQueryKeys';
import { dataQualityQueryKeys } from '../../../resources/js/features/data-quality/queries/dataQualityQueryKeys';
import { debtReviewQueryKeys } from '../../../resources/js/features/debt-review/queries/debtReviewQueryKeys';
import { financialReviewQueryKeys } from '../../../resources/js/features/financial-review/queries/financialReviewQueryKeys';

describe('review workspace query keys', () => {
    it('keeps feature caches isolated while retaining filing and version identity', () => {
        const financial = financialReviewQueryKeys.list('filing-42', { page: 1, perPage: 25 });
        const mapping = conceptMappingQueryKeys.list({ page: 1, perPage: 25 });
        const quality = dataQualityQueryKeys.list('filing-42', 'dataset-v2', 'rules-v3', { page: 1, perPage: 25 });
        const debt = debtReviewQueryKeys.list('filing-42', { page: 1, perPage: 25 });

        expect(new Set([financial[0], mapping[0], quality[0], debt[0]]).size).toBe(4);
        expect(financial).toContain('filing-42');
        expect(quality).toEqual(expect.arrayContaining(['filing-42', 'dataset-v2', 'rules-v3']));
    });
});
