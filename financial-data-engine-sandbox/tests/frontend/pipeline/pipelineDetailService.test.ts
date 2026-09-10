import { afterEach, describe, expect, it, vi } from 'vitest';

import { fetchPipelineFilingDetail, fetchPipelineFilingHistory } from '../../../resources/js/features/pipeline/api/pipelineService';

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('pipeline detail service', () => {
    it('reads the detail endpoint on demand without a storage path', async () => {
        const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify({
            data: {
                filingId: 'FIL-DETAIL-001', issuerCode: 'HSSA', reportType: 'ANNUAL', fiscalYear: 2025, fiscalPeriod: 'FY', periodStart: null, periodEnd: '2025-12-31', revisionNumber: 1,
                processingStage: 'FAILED', qualityStatus: 'REVIEW_REQUIRED', currentRun: null, stageAttempts: [], latestError: null,
                artifacts: [{ artifactId: 'ART-001', artifactType: 'XBRL_INSTANCE', filename: 'source.zip', contentType: 'application/zip', sizeBytes: 100, downloadedAt: '2026-09-09T09:00:00+00:00' }],
            },
        }), { status: 200 }));
        vi.stubGlobal('fetch', fetchMock);

        const detail = await fetchPipelineFilingDetail('FIL-DETAIL-001');

        expect(fetchMock).toHaveBeenCalledWith('/ops/data/pipeline-filings/FIL-DETAIL-001', expect.objectContaining({ credentials: 'same-origin' }));
        expect(detail.artifacts[0]?.filename).toBe('source.zip');
        expect(JSON.stringify(detail)).not.toContain('storage_path');
    });

    it('uses bounded history pagination in the internal read contract', async () => {
        const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify({
            data: [{ id: 'auditEvent:5', type: 'auditEvent', occurredAt: '2026-09-09T10:00:00+00:00', correlationId: null, status: null, action: 'pipeline.reviewed', actorId: 'operator-42', rationale: 'Reviewed', stage: null, attempt: null, error: null }],
            meta: { currentPage: 2, perPage: 10, lastPage: 3, total: 21 },
        }), { status: 200 }));
        vi.stubGlobal('fetch', fetchMock);

        const history = await fetchPipelineFilingHistory('FIL-DETAIL-001', { page: 2, perPage: 10 });

        expect(fetchMock).toHaveBeenCalledWith('/ops/data/pipeline-filings/FIL-DETAIL-001/history?page=2&per_page=10', expect.any(Object));
        expect(history.meta.total).toBe(21);
        expect(history.data[0]?.actorId).toBe('operator-42');
    });
});
