// @vitest-environment jsdom

import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import FilterBar from '../../../resources/js/components/ops/FilterBar.vue';
import Pagination from '../../../resources/js/components/ops/Pagination.vue';
import SearchInput from '../../../resources/js/components/ops/SearchInput.vue';
import Toolbar from '../../../resources/js/components/ops/Toolbar.vue';

describe('shared operations primitives', () => {
    it('renders a labeled filter region without owning filter behavior', () => {
        const wrapper = mount(FilterBar, {
            props: { label: 'Pipeline filters' },
            slots: { default: '<input aria-label="Search filings" />' },
        });

        expect(wrapper.get('section').attributes('aria-label')).toBe('Pipeline filters');
        expect(wrapper.get('input').attributes('aria-label')).toBe('Search filings');
    });

    it('emits controlled search input updates and native changes', async () => {
        const wrapper = mount(SearchInput, {
            props: { id: 'fact-search', label: 'Search facts', modelValue: '' },
        });

        await wrapper.get('input').setValue('revenue');
        await wrapper.get('input').trigger('change');

        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['revenue']);
        expect(wrapper.emitted('change')).toBeDefined();
    });

    it('renders a shared pagination range and emits bounded page changes', async () => {
        const wrapper = mount(Pagination, {
            props: { currentPage: 2, lastPage: 3, total: 125, perPage: 50, totalLabel: 'facts' },
        });

        expect(wrapper.text()).toContain('51–100 of 125 facts');
        await wrapper.get('button[aria-label="Next page"]').trigger('click');
        expect(wrapper.emitted('change-page')).toEqual([[3]]);
    });

    it('provides a consistent toolbar composition surface', () => {
        const wrapper = mount(Toolbar, {
            slots: {
                title: '<h2>Normalized facts</h2>',
                description: '<p>Values retain source precision.</p>',
                actions: '<button>Inspect</button>',
            },
        });

        expect(wrapper.get('h2').text()).toBe('Normalized facts');
        expect(wrapper.get('button').text()).toBe('Inspect');
    });
});
