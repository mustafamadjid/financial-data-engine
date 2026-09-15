<script setup lang="ts">
import { computed } from 'vue';

import { humanize } from '../../ops/utils/formatters';
import type { PipelineStageSummary } from '../types/pipeline';

const props = defineProps<{
    summary?: PipelineStageSummary | null;
}>();

const label = computed(() => {
    const labels: Partial<Record<PipelineStageSummary['status'], string>> = {
        NOT_STARTED: 'Not evaluated',
        QUEUED: 'Queued',
        RUNNING: 'Processing',
        SUCCEEDED: 'Succeeded',
        FAILED: 'Failed',
    };

    return props.summary?.status === undefined ? 'Not available' : labels[props.summary.status] ?? humanize(props.summary.status);
});

const badgeClass = computed(() => {
    const classes: Partial<Record<PipelineStageSummary['status'], string>> = {
        NOT_STARTED: 'border-hissa-border bg-hissa-surface-muted text-hissa-secondary',
        QUEUED: 'border-hissa-warning/25 bg-hissa-warning-soft text-hissa-warning',
        RUNNING: 'border-hissa-info/25 bg-hissa-info-soft text-hissa-info',
        SUCCEEDED: 'border-hissa-success/25 bg-hissa-success-soft text-hissa-success',
        FAILED: 'border-hissa-danger/25 bg-hissa-danger-soft text-hissa-danger',
    };

    return classes[props.summary?.status ?? ''] ?? 'border-hissa-border bg-hissa-surface-muted text-hissa-secondary';
});
</script>

<template>
    <div class="flex min-h-10 min-w-[86px] flex-col items-center justify-center gap-1 text-center">
        <span
            class="inline-flex min-h-6 items-center justify-center whitespace-nowrap rounded-full border px-2 py-0.5 text-[11px] font-semibold leading-4"
            :class="badgeClass"
            :aria-label="`${summary?.stage ?? 'Stage'}: ${label}`"
        >
            {{ label }}
        </span>
        <span v-if="summary?.attempt !== null && summary?.attempt !== undefined" class="ops-meta whitespace-nowrap text-hissa-muted">Attempt {{ summary.attempt }}</span>
    </div>
</template>
