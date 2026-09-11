import { effectScope, nextTick } from 'vue';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { useDebouncedValue } from '../../../resources/js/features/ops/composables/useDebouncedValue';
import { useSelection } from '../../../resources/js/features/ops/composables/useSelection';
import { useUrlFilters } from '../../../resources/js/features/ops/composables/useUrlFilters';

afterEach(() => {
    vi.useRealTimers();
});

describe('shared Ops composables', () => {
    it('cancels a pending debounce when its effect scope is disposed', async () => {
        vi.useFakeTimers();
        const scope = effectScope();
        const state = scope.run(() => useDebouncedValue('', 300));
        if (state === undefined) throw new Error('Expected debounced state.');

        state.input.value = 'new search';
        await nextTick();
        scope.stop();
        vi.advanceTimersByTime(300);

        expect(state.value).toBe('');
    });

    it('serializes URL filter changes as a side effect without deep mutation', async () => {
        const updates: string[] = [];
        const scope = effectScope();
        const filters = scope.run(() => useUrlFilters({
            search: '?status=FAILED&page=3',
            parse: (search) => {
                const query = new URLSearchParams(search);
                return { status: query.get('status'), page: Number(query.get('page') ?? 1) };
            },
            serialize: (value) => `?status=${value.status ?? ''}&page=${value.page}`,
            onUrlChange: (query) => updates.push(query),
        }));
        if (filters === undefined) throw new Error('Expected URL filters.');

        filters.update({ page: 1 });
        await nextTick();

        expect(filters.params.value).toEqual({ status: 'FAILED', page: 1 });
        expect(updates).toEqual(['?status=FAILED&page=1']);
        scope.stop();
    });

    it('derives selection state and clears it explicitly', () => {
        const selection = useSelection<string>();
        selection.select('fact-42');
        expect(selection.hasSelection.value).toBe(true);
        expect(selection.selectedId.value).toBe('fact-42');
        selection.clear();
        expect(selection.hasSelection.value).toBe(false);
    });
});
