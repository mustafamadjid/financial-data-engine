// @vitest-environment jsdom

import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { markReviewItem } from '../../resources/js/features/review-items/api/reviewItemService';
import MarkReviewDialog from '../../resources/js/features/review-items/components/MarkReviewDialog.vue';

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('review item service', () => {
    it('posts the review payload and decodes the accepted item', async () => {
        const payload = {
            entityType: 'normalized_fact' as const,
            entityId: 'NF-REVIEW-001',
            filingId: 'FIL-REVIEW-001',
            expectedVersion: '1.0.0@mapping-1|MAP-REVIEW-001|1',
            rationale: 'The normalized value requires analyst review.',
            idempotencyKey: 'review-request-NF-REVIEW-001',
        };
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify({
            data: {
                reviewItemId: 'REV-001',
                entityType: 'normalized_fact',
                entityId: 'NF-REVIEW-001',
                filingId: 'FIL-REVIEW-001',
                status: 'OPEN',
                rationale: payload.rationale,
                expectedVersion: payload.expectedVersion,
                createdBy: 'system',
                createdAt: '2026-09-15T02:00:00+00:00',
                active: true,
            },
        }), { status: 201 })));

        await expect(markReviewItem(payload)).resolves.toMatchObject({
            reviewItemId: 'REV-001',
            entityId: 'NF-REVIEW-001',
            active: true,
        });

        const [url, init] = vi.mocked(fetch).mock.calls[0] ?? [];
        expect(url).toBe('/ops/actions/review-items');
        expect(init).toMatchObject({
            method: 'POST',
            body: JSON.stringify({
                entity_type: 'normalized_fact',
                entity_id: 'NF-REVIEW-001',
                filing_id: 'FIL-REVIEW-001',
                expected_version: payload.expectedVersion,
                rationale: payload.rationale,
                idempotency_key: payload.idempotencyKey,
            }),
        });
    });

    it('explains a disabled capability and does not offer submit', () => {
        const wrapper = mount(MarkReviewDialog, {
            props: {
                open: true,
                entityType: 'normalized_fact',
                entityId: 'NF-REVIEW-001',
                filingId: 'FIL-REVIEW-001',
                expectedVersion: 'version-1',
                capability: { allowed: false, reasonCode: 'REVIEW_NOT_ELIGIBLE', reason: 'This fact is not eligible for review.' },
            },
            global: {
                plugins: [[VueQueryPlugin, { queryClient: new QueryClient() }]],
            },
        });

        expect(wrapper.text()).toContain('This fact is not eligible for review.');
        expect(wrapper.find('button[type="submit"]').exists()).toBe(false);
    });
});
