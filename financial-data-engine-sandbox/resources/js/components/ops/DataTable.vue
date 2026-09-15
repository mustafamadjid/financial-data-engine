<script setup lang="ts">
import { onMounted, onUpdated, ref } from 'vue';

withDefaults(defineProps<{ minWidthClass?: string; captionClass?: string; scrollLabel?: string }>(), {
    minWidthClass: 'min-w-[960px]',
    captionClass: 'sr-only',
    scrollLabel: 'Scrollable data table',
});

const table = ref<HTMLTableElement | null>(null);

function ensureHeaderScopes(): void {
    table.value?.querySelectorAll('thead th').forEach((header) => {
        if (!header.hasAttribute('scope')) header.setAttribute('scope', 'col');
    });
    table.value?.querySelectorAll('tbody th').forEach((header) => {
        if (!header.hasAttribute('scope')) header.setAttribute('scope', 'row');
    });
}

onMounted(() => void ensureHeaderScopes());
onUpdated(() => void ensureHeaderScopes());
</script>

<template>
    <div class="ops-table-scroll overflow-x-auto overscroll-x-contain focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-hissa-action" tabindex="0" role="region" :aria-label="scrollLabel">
        <table ref="table" class="ops-table w-full border-separate border-spacing-0 text-left text-[13px] leading-5 text-hissa-primary" :class="minWidthClass">
            <caption :class="captionClass"><slot name="caption" /></caption>
            <slot />
        </table>
    </div>
</template>
