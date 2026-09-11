<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

withDefaults(defineProps<{
    titleId: string;
    descriptionId?: string;
    panelClass?: string;
}>(), {
    descriptionId: undefined,
    panelClass: 'max-w-md',
});

const emit = defineEmits<{ close: [] }>();
const panel = ref<HTMLElement | null>(null);
let returnFocus: HTMLElement | null = null;

function focusableElements(): HTMLElement[] {
    if (panel.value === null) return [];
    return Array.from(panel.value.querySelectorAll<HTMLElement>('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'));
}

function handleKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        event.preventDefault();
        emit('close');
        return;
    }
    if (event.key !== 'Tab') return;
    const elements = focusableElements();
    if (elements.length === 0) {
        event.preventDefault();
        panel.value?.focus();
        return;
    }
    const first = elements[0];
    const last = elements[elements.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last?.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first?.focus();
    }
}

onMounted(async () => {
    returnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    await nextTick();
    (focusableElements()[0] ?? panel.value)?.focus();
});

onBeforeUnmount(() => returnFocus?.focus());
</script>

<template>
    <div class="fixed inset-0 z-[60] grid place-items-center bg-black/40 p-4" role="presentation" @mousedown.self="emit('close')">
        <section ref="panel" role="dialog" aria-modal="true" :aria-labelledby="titleId" :aria-describedby="descriptionId" tabindex="-1" class="w-full rounded-xl bg-hissa-surface p-6 shadow-xl outline-none" :class="panelClass" @keydown="handleKeydown">
            <slot />
        </section>
    </div>
</template>
