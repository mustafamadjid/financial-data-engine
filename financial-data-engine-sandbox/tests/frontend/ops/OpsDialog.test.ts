// @vitest-environment jsdom

import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { afterEach, describe, expect, it } from 'vitest';

import OpsDialog from '../../../resources/js/components/ops/OpsDialog.vue';

afterEach(() => {
    document.body.innerHTML = '';
});

describe('OpsDialog', () => {
    it('traps keyboard focus and requests close with Escape', async () => {
        const trigger = document.createElement('button');
        document.body.append(trigger);
        trigger.focus();

        const wrapper = mount(OpsDialog, {
            attachTo: document.body,
            props: { titleId: 'dialog-title', descriptionId: 'dialog-description' },
            slots: {
                default: '<h2 id="dialog-title">Review</h2><p id="dialog-description">Review details.</p><button id="first">First</button><button id="last">Last</button>',
            },
        });
        await nextTick();

        expect(document.activeElement).toBe(wrapper.get('#first').element);
        wrapper.get<HTMLButtonElement>('#last').element.focus();
        await wrapper.get('[role="dialog"]').trigger('keydown', { key: 'Tab' });
        expect(document.activeElement).toBe(wrapper.get('#first').element);

        wrapper.get<HTMLButtonElement>('#first').element.focus();
        await wrapper.get('[role="dialog"]').trigger('keydown', { key: 'Tab', shiftKey: true });
        expect(document.activeElement).toBe(wrapper.get('#last').element);

        await wrapper.get('[role="dialog"]').trigger('keydown', { key: 'Escape' });
        expect(wrapper.emitted('close')).toHaveLength(1);
        wrapper.unmount();
        expect(document.activeElement).toBe(trigger);
    });
});
