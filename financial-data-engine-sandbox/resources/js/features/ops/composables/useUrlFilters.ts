import { computed, shallowRef, watch, type ComputedRef, type ShallowRef } from 'vue';

interface UrlFilterOptions<TFilters extends object> {
    search?: string;
    parse: (search: string) => TFilters;
    serialize: (filters: Readonly<TFilters>) => string;
    onUrlChange?: (query: string) => void;
}

export interface UrlFilters<TFilters extends object> {
    state: ShallowRef<TFilters>;
    params: ComputedRef<Readonly<TFilters>>;
    update: (patch: Partial<TFilters>) => void;
    replace: (filters: TFilters) => void;
}

export function useUrlFilters<TFilters extends object>(options: UrlFilterOptions<TFilters>): UrlFilters<TFilters> {
    const state = shallowRef(options.parse(options.search ?? '')) as ShallowRef<TFilters>;
    const params = computed<Readonly<TFilters>>(() => state.value);

    watch(state, (next) => options.onUrlChange?.(options.serialize(next)), { deep: false });

    return {
        state,
        params,
        update: (patch) => { state.value = { ...state.value, ...patch }; },
        replace: (filters) => { state.value = filters; },
    };
}
