import { onScopeDispose, ref, watch, type Ref } from 'vue';

export interface DebouncedValue<T> {
    input: Ref<T>;
    readonly value: T;
    flush: () => void;
}

export function useDebouncedValue<T>(initialValue: T, delayMilliseconds = 300): DebouncedValue<T> {
    const input = ref(initialValue) as Ref<T>;
    const value = ref(initialValue) as Ref<T>;
    let timeoutId: ReturnType<typeof setTimeout> | undefined;

    function flush(): void {
        if (timeoutId !== undefined) clearTimeout(timeoutId);
        timeoutId = undefined;
        value.value = input.value;
    }

    watch(input, () => {
        if (timeoutId !== undefined) clearTimeout(timeoutId);
        timeoutId = setTimeout(flush, Math.max(0, delayMilliseconds));
    }, { deep: false });

    onScopeDispose(() => {
        if (timeoutId !== undefined) clearTimeout(timeoutId);
    });

    return {
        input,
        get value(): T { return value.value; },
        flush,
    };
}
