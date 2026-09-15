import { afterEach, describe, expect, it, vi } from 'vitest';

import { OpsHttpError, fetchPipelineFilings } from '../../../resources/js/features/pipeline/api/pipelineService';

const filing = {
    filingId: 'filing-100',
    issuerCode: 'HSSA',
    reportType: 'ANNUAL',
    fiscalYear: 2025,
    fiscalPeriod: 'FY',
    periodStart: '2025-01-01',
    periodEnd: '2025-12-31',
    revisionNumber: 2,
    processingStage: 'VALIDATING',
    qualityStatus: 'REVIEW_REQUIRED',
    stages: {
        DOWNLOAD: { stage: 'DOWNLOAD', status: 'SUCCEEDED', attempt: 1, startedAt: null, finishedAt: null },
        PARSE: { stage: 'PARSE', status: 'SUCCEEDED', attempt: 1, startedAt: null, finishedAt: null },
        NORMALIZE: { stage: 'NORMALIZE', status: 'SUCCEEDED', attempt: 1, startedAt: null, finishedAt: null },
        VALIDATE: { stage: 'VALIDATE', status: 'RUNNING', attempt: 2, startedAt: '2026-09-08T02:42:00+00:00', finishedAt: null },
        PUBLISH: { stage: 'PUBLISH', status: 'NOT_STARTED', attempt: null, startedAt: null, finishedAt: null },
    },
    lastProcessedAt: '2026-09-08T02:42:00+00:00',
    errorSummary: null,
    allowedActions: {
        viewDetail: { allowed: true, reasonCode: null, reason: null },
        viewArtifact: { allowed: true, reasonCode: null, reason: null },
        viewHistory: { allowed: true, reasonCode: null, reason: null },
        retry: { allowed: false, reasonCode: 'NOT_IN_SCOPE', reason: 'Retry is not enabled in this release.' },
        reprocess: { allowed: false, reasonCode: 'NOT_IN_SCOPE', reason: 'Reprocess is not enabled in this release.' },
    },
} as const;

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('fetchPipelineFilings', () => {
    it('serializes feature filters as the internal Ops read contract', async () => {
        const fetchMock = vi.fn().mockResolvedValue(
            new Response(JSON.stringify({ data: [filing], meta: { currentPage: 2, perPage: 50, lastPage: 4, total: 178 } }), {
                status: 200,
                headers: { 'content-type': 'application/json' },
            }),
        );
        vi.stubGlobal('fetch', fetchMock);

        const result = await fetchPipelineFilings({
            search: 'HSSA',
            processingStage: 'VALIDATING',
            qualityStatus: 'REVIEW_REQUIRED',
            period: 'FY 2025',
            sort: 'issuer_asc',
            page: 2,
            perPage: 50,
        });

        expect(fetchMock).toHaveBeenCalledWith(
            '/ops/data/pipeline-filings?search=HSSA&processing_stage=VALIDATING&quality_status=REVIEW_REQUIRED&period=FY+2025&sort=issuer_asc&page=2&per_page=50',
            expect.objectContaining({ credentials: 'omit', headers: { Accept: 'application/json' } }),
        );
        expect(result.data[0]?.filingId).toBe('filing-100');
        expect(result.meta).toEqual({ currentPage: 2, perPage: 50, lastPage: 4, total: 178 });
    });

    it('surfaces a server-safe Ops error instead of an opaque failed response', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue(
                new Response(JSON.stringify({ code: 'UPSTREAM_UNAVAILABLE', message: 'The pipeline service is unavailable.' }), {
                    status: 503,
                    headers: { 'content-type': 'application/json' },
                }),
            ),
        );

        await expect(fetchPipelineFilings({ page: 1, perPage: 25, sort: 'last_processed_desc' })).rejects.toMatchObject<Partial<OpsHttpError>>({
            status: 503,
            code: 'UPSTREAM_UNAVAILABLE',
            message: 'The pipeline service is unavailable.',
        });
    });
});
