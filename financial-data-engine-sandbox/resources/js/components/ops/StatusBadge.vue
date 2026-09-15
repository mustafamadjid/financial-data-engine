<script setup lang="ts">
import { computed } from 'vue';

export type StatusBadgeTone = 'neutral' | 'success' | 'warning' | 'danger' | 'info';

const props = withDefaults(defineProps<{ label: string; tone?: StatusBadgeTone }>(), { tone: 'neutral' });
const toneClass = computed(() => ({
    neutral: 'border-hissa-border bg-hissa-surface-muted text-hissa-secondary',
    success: 'border-hissa-success/25 bg-hissa-success-soft text-hissa-success',
    warning: 'border-hissa-warning/25 bg-hissa-warning-soft text-hissa-warning',
    danger: 'border-hissa-danger/25 bg-hissa-danger-soft text-hissa-danger',
    info: 'border-hissa-info/25 bg-hissa-info-soft text-hissa-info',
}[props.tone]));
</script>

<template>
    <span data-status-badge class="ops-status-badge inline-flex min-h-6 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full border px-2 py-0.5 text-[12px] font-semibold leading-[17px]" :data-tone="tone" :class="toneClass">
        <span aria-hidden="true" class="size-1.5 rounded-full bg-current" />
        {{ label }}
    </span>
</template>
