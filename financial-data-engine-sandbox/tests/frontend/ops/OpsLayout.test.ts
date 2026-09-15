// @vitest-environment jsdom

import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';

import OpsLayout from '../../../resources/js/layouts/OpsLayout.vue';

afterEach(() => {
    document.body.innerHTML = '';
});

describe('OpsLayout', () => {
    it('marks the current workspace and exposes header extension points', () => {
        const wrapper = mount(OpsLayout, {
            props: {
                currentPath: '/ops/pipeline',
                pageId: 'pipeline',
                title: 'Filings & Pipeline',
                description: 'Monitor every filing.',
            },
            slots: {
                search: '<label>Search workspace<input type="search" /></label>',
                status: '<span>Monitoring every minute</span>',
                default: '<section>Pipeline content</section>',
            },
        });

        const activeLink = wrapper.get('[data-navigation="desktop"] a[aria-current="page"]');
        expect(activeLink.attributes('href')).toBe('/ops/pipeline');
        expect(activeLink.text()).toContain('Filings & Pipeline');
        expect(wrapper.get('main').attributes('data-page')).toBe('pipeline');
        expect(wrapper.text()).toContain('Search workspace');
        expect(wrapper.text()).toContain('Monitoring every minute');
    });

    it('opens overlay navigation, focuses its close action, and restores focus on Escape', async () => {
        const wrapper = mount(OpsLayout, {
            attachTo: document.body,
            props: { currentPath: '/ops/pipeline', pageId: 'pipeline', title: 'Pipeline' },
        });
        const menuButton = wrapper.get<HTMLButtonElement>('[aria-label="Open navigation"]');
        menuButton.element.focus();
        await menuButton.trigger('click');

        const overlay = wrapper.get('[data-navigation-overlay]');
        expect(overlay.attributes('role')).toBe('dialog');
        expect(document.activeElement).toBe(overlay.get('[aria-label="Close navigation"]').element);

        await overlay.trigger('keydown', { key: 'Tab', shiftKey: true });
        expect((document.activeElement as HTMLAnchorElement).getAttribute('href')).toBe('/ops/data-quality');
        await overlay.trigger('keydown', { key: 'Tab' });
        expect(document.activeElement).toBe(overlay.get('[aria-label="Close navigation"]').element);

        await overlay.trigger('keydown', { key: 'Escape' });
        expect(wrapper.find('[data-navigation-overlay]').exists()).toBe(false);
        expect(document.activeElement).toBe(menuButton.element);
    });
});
