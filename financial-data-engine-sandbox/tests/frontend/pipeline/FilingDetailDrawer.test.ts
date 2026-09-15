// @vitest-environment jsdom

import { flushPromises, mount } from '@vue/test-utils';
import { VueQueryPlugin } from '@tanstack/vue-query';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { createQueryClient } from '../../../resources/js/bootstrap/queryClient';
import FilingDetailDrawer from '../../../resources/js/features/pipeline/components/FilingDetailDrawer.vue';

function mountDrawer(props: { filingId?: string; initialHistoryOpen?: boolean; initialReprocessStage?: string; reprocessAllowed?: boolean; reprocessReason?: string } = {}) {
    const queryClient = createQueryClient();
    queryClient.setDefaultOptions({ queries: { retry: false } });
    return {
        queryClient,
        wrapper: mount(FilingDetailDrawer, {
            attachTo: document.body,
            props: { filingId: 'FIL-DETAIL-001', reprocessAllowed: true, ...props },
            global: { plugins: [[VueQueryPlugin, { queryClient }]] },
        }),
    };
}

afterEach(() => {
    vi.unstubAllGlobals();
    document.body.innerHTML = '';
});

describe('FilingDetailDrawer', () => {
    it('fetches detail only after opening and closes with Escape', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify({
            data: {
                filingId: 'FIL-DETAIL-001', issuerCode: 'HSSA', reportType: 'ANNUAL', fiscalYear: 2025, fiscalPeriod: 'FY', periodStart: null, periodEnd: '2025-12-31', revisionNumber: 1,
                processingStage: 'FAILED', qualityStatus: 'REVIEW_REQUIRED', currentRun: null, stageAttempts: [], latestError: { code: 'INVALID_XBRL', message: 'Invalid source.' }, artifacts: [],
            },
        }), { status: 200 })));
        const { wrapper, queryClient } = mountDrawer();
        await flushPromises();

        const dialog = document.body.querySelector<HTMLElement>('[role="dialog"]');
        expect(dialog?.textContent).toContain('FIL-DETAIL-001');
        const closeButton = document.body.querySelector<HTMLElement>('[aria-label="Close filing detail"]');
        expect(document.activeElement).toBe(closeButton);
        dialog?.dispatchEvent(new KeyboardEvent('keydown', { bubbles: true, key: 'Tab', shiftKey: true }));
        expect(document.activeElement?.textContent).toContain('Reprocess');
        dialog?.dispatchEvent(new KeyboardEvent('keydown', { bubbles: true, key: 'Tab' }));
        expect(document.activeElement).toBe(closeButton);
        dialog?.dispatchEvent(new KeyboardEvent('keydown', { bubbles: true, key: 'Escape' }));
        expect(wrapper.emitted('close')).toHaveLength(1);
        queryClient.clear();
    });

    it('shows a recoverable detail error without rendering private payload fields', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify({ code: 'REQUEST_FAILED', message: 'Detail unavailable.' }), { status: 500 })));
        const { wrapper, queryClient } = mountDrawer();
        await flushPromises();

        expect(document.body.textContent).toContain('Detail unavailable.');
        expect(document.body.textContent).not.toContain('storage_path');
        queryClient.clear();
    });

    it('explains when reprocess is unavailable instead of exposing the action', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify({
            data: {
                filingId: 'FIL-DETAIL-001', issuerCode: 'HSSA', reportType: 'ANNUAL', fiscalYear: 2025, fiscalPeriod: 'FY', periodStart: null, periodEnd: '2025-12-31', revisionNumber: 1,
                processingStage: 'FAILED', qualityStatus: 'REVIEW_REQUIRED', currentRun: null, stageAttempts: [], latestError: { code: 'INVALID_XBRL', message: 'Invalid source.' }, artifacts: [],
            },
        }), { status: 200 })));
        const { wrapper, queryClient } = mountDrawer({ reprocessAllowed: false, reprocessReason: 'Reprocess is not enabled for this filing.' });
        await flushPromises();

        expect(document.body.textContent).toContain('Reprocess is not enabled for this filing.');
        expect(Array.from(document.body.querySelectorAll('button')).some((button) => button.textContent === 'Reprocess')).toBe(false);
        queryClient.clear();
    });

    it('focuses history when opened in the history state', async () => {
        vi.stubGlobal('fetch', vi.fn((url: string) => Promise.resolve(new Response(JSON.stringify(
            url.includes('/history?')
                ? { data: [{ id: 'history-1', type: 'jobAttempt', occurredAt: '2026-09-08T02:42:00+00:00', correlationId: 'corr-1', status: 'FAILED', action: null, actorId: null, rationale: null, stage: 'PUBLISH', attempt: 1, error: null }], meta: { currentPage: 1, perPage: 10, lastPage: 1, total: 1 } }
                : { data: { filingId: 'FIL-DETAIL-001', issuerCode: 'HSSA', reportType: 'ANNUAL', fiscalYear: 2025, fiscalPeriod: 'FY', periodStart: null, periodEnd: '2025-12-31', revisionNumber: 1, processingStage: 'FAILED', qualityStatus: 'REVIEW_REQUIRED', currentRun: null, stageAttempts: [], latestError: null, artifacts: [] } },
        ), { status: 200 }))));
        const { wrapper, queryClient } = mountDrawer({ initialHistoryOpen: true });
        await flushPromises();
        await flushPromises();

        expect(document.activeElement).toBe(document.body.querySelector('#history-title'));
        queryClient.clear();
    });

    it('shows accepted reprocess feedback inside the drawer', async () => {
        vi.stubGlobal('fetch', vi.fn((url: string) => Promise.resolve(new Response(JSON.stringify(
            url.includes('/reprocess')
                ? { data: { operation: 'reprocess', filingId: 'FIL-DETAIL-001', pipelineRunId: 42, correlationId: 'corr-42', stage: 'VALIDATE' } }
                : { data: { filingId: 'FIL-DETAIL-001', issuerCode: 'HSSA', reportType: 'ANNUAL', fiscalYear: 2025, fiscalPeriod: 'FY', periodStart: null, periodEnd: '2025-12-31', revisionNumber: 1, processingStage: 'FAILED', qualityStatus: 'REVIEW_REQUIRED', currentRun: null, stageAttempts: [], latestError: null, artifacts: [] } },
        ), { status: 200 }))));
        const { wrapper, queryClient } = mountDrawer({ initialReprocessStage: 'VALIDATE' });
        await flushPromises();
        const reprocessButton = Array.from(document.body.querySelectorAll('button')).find((button) => button.textContent === 'Reprocess') as HTMLButtonElement;
        reprocessButton.click();
        await flushPromises();
        await flushPromises();
        const reason = document.body.querySelector<HTMLTextAreaElement>('#reprocess-reason');
        reason!.value = 'Corrected source';
        reason!.dispatchEvent(new Event('input', { bubbles: true }));
        await flushPromises();
        const submit = Array.from(document.body.querySelectorAll('button')).find((button) => button.textContent?.includes('Start reprocess')) as HTMLButtonElement;
        submit.click();
        await flushPromises();

        expect(document.body.textContent).toContain('Reprocess queued for FIL-DETAIL-001.');
        expect(wrapper.emitted('accepted')).toEqual([['reprocess', 'FIL-DETAIL-001']]);
        queryClient.clear();
    });
});
