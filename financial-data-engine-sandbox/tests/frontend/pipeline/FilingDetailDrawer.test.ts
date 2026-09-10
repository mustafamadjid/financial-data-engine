// @vitest-environment jsdom

import { flushPromises, mount } from '@vue/test-utils';
import { VueQueryPlugin } from '@tanstack/vue-query';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { createQueryClient } from '../../../resources/js/bootstrap/queryClient';
import FilingDetailDrawer from '../../../resources/js/features/pipeline/components/FilingDetailDrawer.vue';

function mountDrawer() {
    const queryClient = createQueryClient();
    queryClient.setDefaultOptions({ queries: { retry: false } });
    return {
        queryClient,
        wrapper: mount(FilingDetailDrawer, {
            attachTo: document.body,
            props: { filingId: 'FIL-DETAIL-001' },
            global: { plugins: [[VueQueryPlugin, { queryClient }]] },
        }),
    };
}

afterEach(() => {
    vi.unstubAllGlobals();
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

        expect(wrapper.get('[role="dialog"]').text()).toContain('FIL-DETAIL-001');
        expect(document.activeElement).toBe(wrapper.get('[aria-label="Close filing detail"]').element);
        await wrapper.get('[role="dialog"]').trigger('keydown', { key: 'Escape' });
        expect(wrapper.emitted('close')).toHaveLength(1);
        queryClient.clear();
    });

    it('shows a recoverable detail error without rendering private payload fields', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify({ code: 'REQUEST_FAILED', message: 'Detail unavailable.' }), { status: 500 })));
        const { wrapper, queryClient } = mountDrawer();
        await flushPromises();

        expect(wrapper.text()).toContain('Detail unavailable.');
        expect(wrapper.text()).not.toContain('storage_path');
        queryClient.clear();
    });
});
