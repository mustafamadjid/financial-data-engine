<script setup lang="ts">
import { computed } from 'vue';

import StatusBadge, { type StatusBadgeTone } from '../../../components/ops/StatusBadge.vue';
import { humanize } from '../../ops/utils/formatters';
import type { ValidationSummary as ValidationSummaryData } from '../types/dataQuality';

const props = withDefaults(defineProps<{
    summary?: ValidationSummaryData;
    isLoading: boolean;
    error?: string | null;
}>(), { summary: undefined, error: null });

const qualityTone = computed<StatusBadgeTone>(() => {
    const status = props.summary?.qualityStatus;
    if (status === 'VERIFIED') return 'success';
    if (status === 'REVIEW_REQUIRED') return 'warning';
    if (status === 'FAILED') return 'danger';
    if (status === 'PENDING') return 'info';
    return 'neutral';
});

function qualityStatusLabel(value: string): string {
    return humanize(value);
}

const metrics = computed(() => [
    { label: 'Total checks', value: props.summary?.total ?? null, detail: 'all rule outcomes', tone: 'text-hissa-primary' },
    { label: 'Failed results', value: props.summary?.byResult?.FAIL ?? null, detail: 'result = FAIL', tone: 'text-hissa-danger' },
    { label: 'Error severity', value: props.summary?.bySeverity?.ERROR ?? null, detail: 'severity = ERROR', tone: 'text-hissa-danger' },
    { label: 'Review required', value: props.summary?.byResult?.REVIEW_REQUIRED ?? null, detail: 'needs analyst review', tone: 'text-hissa-warning' },
]);
</script>

<template>
    <section aria-labelledby="validation-summary-title" :aria-busy="isLoading ? 'true' : undefined" class="overflow-hidden rounded-lg border border-hissa-border bg-hissa-surface">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-hissa-border px-4 py-3 sm:px-5">
            <div class="min-w-0">
                <h2 id="validation-summary-title" class="ops-section-title">Quality posture</h2>
                <p class="mt-1 text-sm text-hissa-secondary">Triage the selected execution before opening individual results.</p>
            </div>
            <StatusBadge v-if="summary" :label="qualityStatusLabel(summary.qualityStatus)" :tone="qualityTone" />
            <span v-else-if="isLoading" class="ops-meta text-hissa-muted">Loading summary</span>
        </div>

        <div class="grid grid-cols-2 divide-x divide-y divide-hissa-border sm:grid-cols-4 sm:divide-y-0">
            <article v-for="metric in metrics" :key="metric.label" class="min-w-0 px-4 py-3 sm:px-5">
                <p class="ops-meta text-hissa-secondary">{{ metric.label }}</p>
                <span v-if="isLoading" aria-hidden="true" class="mt-1 block h-7 w-12 animate-pulse rounded-sm bg-hissa-subtle" />
                <p v-else class="ops-numeric mt-1 text-[22px] font-semibold leading-7" :class="metric.tone">{{ metric.value ?? '—' }}</p>
                <p class="mt-0.5 ops-meta text-hissa-muted">{{ metric.detail }}</p>
            </article>
        </div>

        <p v-if="error" role="status" class="border-t border-hissa-border bg-hissa-surface-subtle px-4 py-2.5 text-sm text-hissa-secondary sm:px-5">Summary unavailable. The result list remains available for inspection.</p>
    </section>
</template>
