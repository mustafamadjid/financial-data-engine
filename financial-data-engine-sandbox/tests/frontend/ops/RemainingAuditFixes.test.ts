// @vitest-environment jsdom

import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import ValidationDetailDialog from '../../../resources/js/features/data-quality/components/ValidationDetailDialog.vue';

describe('remaining audit hardening', () => {
    it('renders an unavailable fact destination as text instead of a no-op link', () => {
        const wrapper = mount(ValidationDetailDialog, {
            props: {
                validationResultId: 'VR-001',
                loading: false,
                error: null,
                detail: {
                    validationResultId: 'VR-001',
                    filingId: 'FIL-001',
                    normalizedDatasetVersion: 'dataset-v1',
                    validationRuleSetVersion: 'rules-v1',
                    rule: { code: 'RULE-001', version: 1, description: 'Rule description' },
                    result: 'FAIL',
                    severity: 'ERROR',
                    expectedValue: null,
                    actualValue: null,
                    tolerance: null,
                    message: 'Validation failed.',
                    checkedAt: '2026-09-15T02:00:00Z',
                    reviewVersion: 'review-v1',
                    normalizedFactIds: ['NF-001'],
                    inputFacts: [{ normalizedFactId: 'NF-001', canonicalConcept: 'Revenue', value: '10', currency: 'USD', sourceConcept: 'us-gaap:Revenue', href: null as unknown as string }],
                    allowedActions: { markForReview: { allowed: false, reasonCode: null, reason: null } },
                    filing: { filing_id: 'FIL-001', issuer_code: 'HSSA', fiscal_period: 'FY', revision_number: 1 },
                },
            },
        });

        expect(wrapper.find('a[href="#"]').exists()).toBe(false);
        expect(wrapper.text()).toContain('NF-001');
    });
});
