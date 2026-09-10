// @vitest-environment jsdom

import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import PipelineTable from '../../../resources/js/features/pipeline/components/PipelineTable.vue';

const item = {
    filingId: 'filing-stable-42',
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
        PARSE: { stage: 'PARSE', status: 'QUEUED', attempt: 1, startedAt: null, finishedAt: null },
        NORMALIZE: { stage: 'NORMALIZE', status: 'NOT_STARTED', attempt: null, startedAt: null, finishedAt: null },
        VALIDATE: { stage: 'VALIDATE', status: 'RUNNING', attempt: 2, startedAt: '2026-09-08T02:42:00+00:00', finishedAt: null },
        PUBLISH: { stage: 'PUBLISH', status: 'FAILED', attempt: 1, startedAt: null, finishedAt: '2026-09-08T02:43:00+00:00' },
    },
    lastProcessedAt: '2026-09-08T02:42:00+00:00',
    errorSummary: { code: 'PUBLISH_FAILED', message: 'The publish target was unavailable.', stage: 'PUBLISH' },
    allowedActions: {
        viewDetail: { allowed: true, reasonCode: null, reason: null },
        viewArtifact: { allowed: false, reasonCode: 'ARTIFACT_NOT_AVAILABLE', reason: 'No stored source artifact is available.' },
        viewHistory: { allowed: true, reasonCode: null, reason: null },
        retry: { allowed: false, reasonCode: 'NOT_IN_SCOPE', reason: 'Retry is not enabled in this release.' },
        reprocess: { allowed: false, reasonCode: 'NOT_IN_SCOPE', reason: 'Reprocess is not enabled in this release.' },
    },
} as const;

describe('PipelineTable', () => {
    it('renders visible stage labels and a row identity derived from filingId', async () => {
        const wrapper = mount(PipelineTable, { props: { rows: [item], hasActiveFilters: false } });

        expect(wrapper.get('tbody tr').attributes('data-filing-id')).toBe('filing-stable-42');
        expect(wrapper.text()).toContain('Succeeded');
        expect(wrapper.text()).toContain('Queued');
        expect(wrapper.text()).toContain('Processing');
        expect(wrapper.text()).toContain('Failed');

        await wrapper.get('button[aria-label="View detail for HSSA filing-stable-42"]').trigger('click');
        expect(wrapper.emitted('view-detail')).toEqual([['filing-stable-42']]);
    });

    it('distinguishes a fresh list from a filtered list with no matching records', () => {
        const empty = mount(PipelineTable, { props: { rows: [], hasActiveFilters: false } });
        const filtered = mount(PipelineTable, { props: { rows: [], hasActiveFilters: true } });

        expect(empty.text()).toContain('No filings have been processed yet.');
        expect(filtered.text()).toContain('No filings match the current filters.');
    });
});
