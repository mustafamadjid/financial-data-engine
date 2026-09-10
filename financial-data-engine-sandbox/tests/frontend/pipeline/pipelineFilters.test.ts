import { effectScope, nextTick } from 'vue';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { usePipelineFilters } from '../../../resources/js/features/pipeline/composables/usePipelineFilters';

afterEach(() => {
    vi.useRealTimers();
});

describe('usePipelineFilters', () => {
    it('debounces search, resets pagination, and synchronizes the URL with server filters', async () => {
        vi.useFakeTimers();
        const urlUpdates: string[] = [];
        const scope = effectScope();
        const filters = scope.run(() =>
            usePipelineFilters({
                search: '?search=legacy&page=3&per_page=50&quality_status=FAILED',
                onUrlChange: (query) => urlUpdates.push(query),
            }),
        );

        if (filters === undefined) {
            throw new Error('Expected filters to be initialized.');
        }

        filters.searchInput.value = 'HSSA';
        await nextTick();
        vi.advanceTimersByTime(299);
        expect(filters.params.value).toMatchObject({ search: 'legacy', page: 3, perPage: 50, qualityStatus: 'FAILED' });

        vi.advanceTimersByTime(1);
        await nextTick();

        expect(filters.params.value).toMatchObject({ search: 'HSSA', page: 1, perPage: 50, qualityStatus: 'FAILED' });
        expect(urlUpdates.at(-1)).toBe('?search=HSSA&quality_status=FAILED&sort=last_processed_desc&page=1&per_page=50');
        scope.stop();
    });

    it('resets pagination immediately for a select filter but does not add empty values to the URL', async () => {
        const urlUpdates: string[] = [];
        const scope = effectScope();
        const filters = scope.run(() =>
            usePipelineFilters({
                search: '?page=4&sort=issuer_asc',
                onUrlChange: (query) => urlUpdates.push(query),
            }),
        );

        if (filters === undefined) {
            throw new Error('Expected filters to be initialized.');
        }

        filters.qualityStatus.value = 'VERIFIED';
        await nextTick();

        expect(filters.params.value).toMatchObject({ page: 1, qualityStatus: 'VERIFIED', sort: 'issuer_asc' });
        expect(urlUpdates.at(-1)).toBe('?quality_status=VERIFIED&sort=issuer_asc&page=1&per_page=25');
        scope.stop();
    });

    it('cancels a pending search debounce when the feature scope is disposed', async () => {
        vi.useFakeTimers();
        const urlUpdates: string[] = [];
        const scope = effectScope();
        const filters = scope.run(() => usePipelineFilters({ onUrlChange: (query) => urlUpdates.push(query) }));

        if (filters === undefined) {
            throw new Error('Expected filters to be initialized.');
        }

        filters.searchInput.value = 'do-not-request';
        await nextTick();
        scope.stop();
        vi.advanceTimersByTime(300);

        expect(urlUpdates).toEqual([]);
    });
});
