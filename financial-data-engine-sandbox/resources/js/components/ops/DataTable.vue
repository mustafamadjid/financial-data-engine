<script setup lang="ts">
import { onMounted, onUpdated, ref } from 'vue';

withDefaults(defineProps<{ minWidthClass?: string; captionClass?: string }>(), {
    minWidthClass: 'min-w-[960px]',
    captionClass: 'sr-only',
});

const table = ref<HTMLTableElement | null>(null);

function ensureHeaderScopes(): void {
    table.value?.querySelectorAll('thead th').forEach((header) => {
        if (!header.hasAttribute('scope')) header.setAttribute('scope', 'col');
    });
}

onMounted(() => void ensureHeaderScopes());
onUpdated(() => void ensureHeaderScopes());
</script>

<template>
    <div class="overflow-x-auto">
        <table ref="table" class="w-full border-separate border-spacing-0 text-left text-sm" :class="minWidthClass">
            <caption :class="captionClass"><slot name="caption" /></caption>
            <slot />
        </table>
    </div>
</template>
