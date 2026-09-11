<script setup lang="ts">
import { computed } from 'vue';

export type StatusBadgeTone = 'neutral' | 'success' | 'warning' | 'danger' | 'info';

const props = withDefaults(defineProps<{ label: string; tone?: StatusBadgeTone }>(), { tone: 'neutral' });
const toneClass = computed(() => ({
    neutral: 'border-hissa-border bg-hissa-subtle text-hissa-secondary',
    success: 'border-transparent bg-hissa-secondary-soft text-hissa-action',
    warning: 'border-transparent bg-hissa-warning-soft text-hissa-warning',
    danger: 'border-transparent bg-red-50 text-hissa-danger',
    info: 'border-transparent bg-blue-50 text-hissa-info',
}[props.tone]));
</script>

<template>
    <span class="inline-flex min-h-7 items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold leading-[18px]" :class="toneClass">
        <span aria-hidden="true" class="size-1.5 rounded-full bg-current" />
        {{ label }}
    </span>
</template>
