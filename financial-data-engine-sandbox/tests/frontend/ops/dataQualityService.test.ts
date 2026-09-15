import { afterEach, describe, expect, it, vi } from 'vitest';

import { fetchValidationSummary } from '../../../resources/js/features/data-quality/api/dataQualityService';

describe('data quality service', () => {
    afterEach(() => vi.unstubAllGlobals());

    it('requests summary with the complete execution identity', async () => {
        const fetchMock = vi.fn(async (input: RequestInfo | URL) => {
            expect(String(input)).toContain('/ops/data/validation-summary?');
            expect(String(input)).toContain('filing_id=FIL-1');
            expect(String(input)).toContain('dataset_version=dataset-v2');
            expect(String(input)).toContain('rule_set_version=rules-v3');
            return new Response(JSON.stringify({ data: {
                filingId: 'FIL-1', normalizedDatasetVersion: 'dataset-v2', validationRuleSetVersion: 'rules-v3', total: 1,
                byResult: { PASS: 1, FAIL: 0, REVIEW_REQUIRED: 0, SKIPPED: 0 },
                bySeverity: { ERROR: 0, WARN: 0, INFO: 1 }, qualityStatus: 'VERIFIED', verifiedInvariant: true,
            } }), { status: 200, headers: { 'Content-Type': 'application/json' } });
        });
        vi.stubGlobal('fetch', fetchMock);

        const summary = await fetchValidationSummary('FIL-1', 'dataset-v2', 'rules-v3');

        expect(summary.verifiedInvariant).toBe(true);
        expect(fetchMock).toHaveBeenCalledOnce();
    });
});
