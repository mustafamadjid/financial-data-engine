<script setup lang="ts">
withDefaults(defineProps<{
    label?: string;
    contentClass?: string;
}>(), {
    label: 'Filters',
    contentClass: 'flex flex-wrap items-end gap-3',
});
</script>

<template>
    <section data-ops-filter-bar class="rounded-lg border border-hissa-border bg-hissa-surface p-4 sm:p-5" :aria-label="label">
        <div :class="contentClass">
            <slot />
        </div>
        <div v-if="$slots.footer" class="mt-3">
            <slot name="footer" />
        </div>
    </section>
</template>
