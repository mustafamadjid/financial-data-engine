<script setup lang="ts">
import { computed } from 'vue';

import type { PipelineStageSummary } from '../types/pipeline';

const props = defineProps<{
    summary: PipelineStageSummary;
}>();

const label = computed(() => {
    const labels: Record<PipelineStageSummary['status'], string> = {
        NOT_STARTED: 'Not evaluated',
        QUEUED: 'Queued',
        RUNNING: 'Processing',
        SUCCEEDED: 'Succeeded',
        FAILED: 'Failed',
    };

    return labels[props.summary.status];
});

const badgeClass = computed(() => {
    const classes: Record<PipelineStageSummary['status'], string> = {
        NOT_STARTED: 'border-hissa-border bg-hissa-subtle text-hissa-secondary',
        QUEUED: 'border-transparent bg-hissa-warning-soft text-hissa-warning',
        RUNNING: 'border-transparent bg-blue-50 text-hissa-info',
        SUCCEEDED: 'border-transparent bg-hissa-secondary-soft text-hissa-action',
        FAILED: 'border-transparent bg-red-50 text-hissa-danger',
    };

    return classes[props.summary.status];
});
</script>

<template>
    <span
        class="inline-flex min-w-[78px] items-center justify-center rounded-full border px-2 py-1 text-xs leading-[18px] font-medium"
        :class="badgeClass"
        :aria-label="`${summary.stage}: ${label}`"
    >
        {{ label }}
    </span>
</template>
