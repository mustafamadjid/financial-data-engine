// @vitest-environment jsdom

import { flushPromises, mount } from '@vue/test-utils';
import { VueQueryPlugin } from '@tanstack/vue-query';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { createQueryClient } from '../../../resources/js/bootstrap/queryClient';
import PipelineIndex from '../../../resources/js/Pages/Pipeline/Index.vue';

const filing = {
    filingId: 'filing-42', issuerCode: 'HSSA', reportType: 'ANNUAL', fiscalYear: 2025, fiscalPeriod: 'FY', periodStart: null, periodEnd: '2025-12-31', revisionNumber: 1,
    processingStage: 'VALIDATING', qualityStatus: 'PENDING', lastProcessedAt: null, errorSummary: null,
    stages: {
        DOWNLOAD: { stage: 'DOWNLOAD', status: 'SUCCEEDED', attempt: 1, startedAt: null, finishedAt: null }, PARSE: { stage: 'PARSE', status: 'SUCCEEDED', attempt: 1, startedAt: null, finishedAt: null },
        NORMALIZE: { stage: 'NORMALIZE', status: 'SUCCEEDED', attempt: 1, startedAt: null, finishedAt: null }, VALIDATE: { stage: 'VALIDATE', status: 'RUNNING', attempt: 1, startedAt: null, finishedAt: null },
        PUBLISH: { stage: 'PUBLISH', status: 'NOT_STARTED', attempt: null, startedAt: null, finishedAt: null },
    },
    allowedActions: {
        viewDetail: { allowed: true, reasonCode: null, reason: null }, viewArtifact: { allowed: false, reasonCode: 'ARTIFACT_NOT_AVAILABLE', reason: 'No stored source artifact is available.' },
        viewHistory: { allowed: true, reasonCode: null, reason: null }, retry: { allowed: false, reasonCode: 'NOT_IN_SCOPE', reason: 'Retry is not enabled in this release.' },
        reprocess: { allowed: false, reasonCode: 'NOT_IN_SCOPE', reason: 'Reprocess is not enabled in this release.' },
    },
};

function mountPage() {
    const queryClient = createQueryClient();
    return {
        queryClient,
        wrapper: mount(PipelineIndex, { global: { plugins: [[VueQueryPlugin, { queryClient }]] } }),
    };
}

afterEach(() => {
    vi.unstubAllGlobals();
    window.history.replaceState({}, '', '/ops/pipeline');
});

describe('PipelineIndex', () => {
    it('shows a loading state while list and summary start independently', async () => {
        const fetchMock = vi.fn((url: string) => {
            if (url.startsWith('/ops/data/pipeline-summary')) {
                return Promise.resolve(new Response(JSON.stringify({ data: { total: 1, active: 1, failed: 0, verified: 0, reviewRequired: 0, pending: 1, failedExecutions: 0 } }), { status: 200 }));
            }
            return Promise.resolve(new Response(JSON.stringify({ data: [filing], meta: { currentPage: 1, perPage: 25, lastPage: 1, total: 1 } }), { status: 200 }));
        });
        vi.stubGlobal('fetch', fetchMock);

        const { wrapper, queryClient } = mountPage();
        expect(wrapper.get('[aria-label="Loading pipeline filings"]').exists()).toBe(true);
        await flushPromises();

        expect(wrapper.get('[data-navigation="desktop"] a[aria-current="page"]').text()).toContain('Filings & Pipeline');
        expect(fetchMock.mock.calls.map(([url]) => url)).toContain('/ops/data/pipeline-summary');
        expect(fetchMock.mock.calls.map(([url]) => url)).toContain('/ops/data/pipeline-filings?sort=last_processed_desc&page=1&per_page=25');
        expect(wrapper.text()).toContain('filing-42');
        queryClient.clear();
    });

    it('keeps the page mounted and explains a failed list response', async () => {
        vi.stubGlobal('fetch', vi.fn((url: string) => Promise.resolve(
            url.startsWith('/ops/data/pipeline-summary')
                ? new Response(JSON.stringify({ data: { total: 0, active: 0, failed: 0, verified: 0, reviewRequired: 0, pending: 0, failedExecutions: 0 } }), { status: 200 })
                : new Response(JSON.stringify({ code: 'REQUEST_INVALID', message: 'Pipeline request is invalid.' }), { status: 422 }),
        )));

        const { wrapper, queryClient } = mountPage();
        await flushPromises();

        expect(wrapper.text()).toContain('Pipeline data could not be loaded.');
        expect(wrapper.text()).toContain('Pipeline request is invalid.');
        queryClient.clear();
    });

    it('shows active filter context and clears the filter set in one action', async () => {
        window.history.replaceState({}, '', '/ops/pipeline?search=HSSA&quality_status=FAILED');
        vi.stubGlobal('fetch', vi.fn((url: string) => Promise.resolve(
            url.startsWith('/ops/data/pipeline-summary')
                ? new Response(JSON.stringify({ data: { total: 1, active: 0, failed: 1, verified: 0, reviewRequired: 0, pending: 0, failedExecutions: 1 } }), { status: 200 })
                : new Response(JSON.stringify({ data: [filing], meta: { currentPage: 1, perPage: 25, lastPage: 1, total: 1 } }), { status: 200 }),
        )));

        const { wrapper, queryClient } = mountPage();
        await flushPromises();

        expect(wrapper.text()).toContain('2 filters active');
        expect(wrapper.text()).toContain('Showing 1 of 1 filings');
        await wrapper.get('[aria-label="Clear pipeline filters"]').trigger('click');
        expect((wrapper.get('#pipeline-search').element as HTMLInputElement).value).toBe('');
        queryClient.clear();
    });

    it('confirms when a retry request is accepted', async () => {
        const retryFiling = {
            ...filing,
            retryJobRunId: 7,
            errorSummary: { code: 'PUBLISH_FAILED', message: 'Publish failed.', stage: 'PUBLISH' },
            allowedActions: {
                ...filing.allowedActions,
                retry: { allowed: true, reasonCode: null, reason: null },
            },
        };
        vi.stubGlobal('fetch', vi.fn((url: string) => Promise.resolve(new Response(JSON.stringify(
            url.includes('/retry')
                ? { data: { operation: 'retry', filingId: 'filing-42', pipelineRunId: 42, correlationId: 'corr-42', stage: 'PUBLISH', attempt: 2, jobRunId: 7 } }
                : url.startsWith('/ops/data/pipeline-summary')
                    ? { data: { total: 1, active: 0, failed: 1, verified: 0, reviewRequired: 0, pending: 0, failedExecutions: 1 } }
                    : { data: [retryFiling], meta: { currentPage: 1, perPage: 25, lastPage: 1, total: 1 } },
        ), { status: 200 }))));

        const { wrapper, queryClient } = mountPage();
        await flushPromises();
        await wrapper.get('button[aria-label="Retry failed stage for HSSA filing-42"]').trigger('click');
        await flushPromises();
        await wrapper.get('#retry-reason').setValue('Retry after target recovery');
        const submit = wrapper.findAll('button').find((button) => button.text().includes('Retry stage'));
        await submit!.trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Retry queued for filing-42 at PUBLISH.');
        queryClient.clear();
    });
});
