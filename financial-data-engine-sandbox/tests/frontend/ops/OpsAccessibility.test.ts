// @vitest-environment jsdom

import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import AsyncState from '../../../resources/js/components/ops/AsyncState.vue';
import DataTable from '../../../resources/js/components/ops/DataTable.vue';

describe('Ops accessibility primitives', () => {
    it('announces async state changes to assistive technology', () => {
        const wrapper = mount(AsyncState, {
            props: { state: 'error', title: 'Could not load', message: 'Try again.' },
        });

        expect(wrapper.get('[role="alert"]').attributes('aria-live')).toBe('assertive');
        expect(wrapper.get('[role="alert"]').attributes('aria-atomic')).toBe('true');
    });

    it('keeps table captions and column headers available semantically', () => {
        const wrapper = mount(DataTable, {
            slots: {
                caption: 'Financial facts',
                default: '<thead><tr><th>Fact</th></tr></thead><tbody><tr><td>NF-001</td></tr></tbody>',
            },
        });

        expect(wrapper.get('caption').text()).toBe('Financial facts');
        expect(wrapper.get('th').attributes('scope')).toBe('col');
    });
});
