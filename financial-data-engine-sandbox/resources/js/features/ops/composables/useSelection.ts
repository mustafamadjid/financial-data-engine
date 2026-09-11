import { computed, ref, type ComputedRef, type Ref } from 'vue';

export interface Selection<TIdentifier extends string | number> {
    selectedId: Ref<TIdentifier | null>;
    hasSelection: ComputedRef<boolean>;
    select: (identifier: TIdentifier) => void;
    clear: () => void;
}

export function useSelection<TIdentifier extends string | number>(): Selection<TIdentifier> {
    const selectedId = ref<TIdentifier | null>(null) as Ref<TIdentifier | null>;
    const hasSelection = computed(() => selectedId.value !== null);
    return {
        selectedId,
        hasSelection,
        select: (identifier) => { selectedId.value = identifier; },
        clear: () => { selectedId.value = null; },
    };
}
