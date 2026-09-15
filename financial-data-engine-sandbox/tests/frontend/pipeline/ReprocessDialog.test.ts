// @vitest-environment jsdom

import { flushPromises, mount } from '@vue/test-utils';
import { VueQueryPlugin } from '@tanstack/vue-query';
import { describe, expect, it } from 'vitest';

import { createQueryClient } from '../../../resources/js/bootstrap/queryClient';
import ReprocessDialog from '../../../resources/js/features/pipeline/components/ReprocessDialog.vue';

function mountDialog(initialStage?: string) {
    const queryClient = createQueryClient();
    return {
        queryClient,
        wrapper: mount(ReprocessDialog, {
            props: { filingId: 'FIL-REPROCESS-001', initialStage },
            global: { plugins: [[VueQueryPlugin, { queryClient }]] },
        }),
    };
}

describe('ReprocessDialog', () => {
    it('requires an explicit start stage when none is supplied', async () => {
        const { wrapper, queryClient } = mountDialog();
        await flushPromises();

        expect((wrapper.get('#reprocess-stage').element as HTMLSelectElement).value).toBe('');
        expect(wrapper.get('button:last-child').attributes('disabled')).toBeDefined();
        queryClient.clear();
    });

    it('uses the failed stage as the initial start stage when supplied', async () => {
        const { wrapper, queryClient } = mountDialog('VALIDATE');
        await flushPromises();

        expect((wrapper.get('#reprocess-stage').element as HTMLSelectElement).value).toBe('VALIDATE');
        queryClient.clear();
    });
});
