<script setup lang="ts">
import type { PipelineSummary } from '../types/pipeline';

defineProps<{
    summary?: PipelineSummary;
    isLoading: boolean;
    error?: string | null;
}>();
</script>

<template>
    <section aria-labelledby="pipeline-summary-title" :aria-busy="isLoading ? 'true' : undefined" class="overflow-hidden rounded-lg border border-hissa-border bg-hissa-surface">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-hissa-border px-4 py-3 sm:px-5">
            <div class="min-w-0">
                <h2 id="pipeline-summary-title" class="ops-section-title">Pipeline posture</h2>
                <p class="mt-1 text-sm text-hissa-secondary">Triage active work and failed executions before opening a filing.</p>
            </div>
            <span v-if="isLoading && summary === undefined" class="ops-meta text-hissa-muted">Loading summary</span>
        </div>

        <div class="grid grid-cols-2 divide-x divide-y divide-hissa-border md:grid-cols-4 md:divide-y-0">
        <article v-for="metric in summary === undefined ? [] : [
            { label: 'Active filings', value: summary.active, tone: 'text-hissa-info' },
            { label: 'Failed executions', value: summary.failedExecutions, tone: 'text-hissa-danger' },
            { label: 'Review required', value: summary.reviewRequired, tone: 'text-hissa-warning' },
            { label: 'Verified', value: summary.verified, tone: 'text-hissa-success' },
        ]" :key="metric.label" class="min-w-0 px-4 py-3 sm:px-5">
            <p class="ops-meta text-hissa-secondary">{{ metric.label }}</p>
            <p class="ops-numeric mt-1 text-[22px] font-semibold leading-7" :class="metric.tone">{{ metric.value ?? '—' }}</p>
        </article>
        <template v-if="isLoading && summary === undefined">
            <article v-for="placeholder in 4" :key="placeholder" class="min-h-[84px] animate-pulse px-4 py-3 sm:px-5">
                <div class="h-3 w-28 rounded-sm bg-hissa-border"></div>
                <div class="mt-3 h-7 w-12 rounded-sm bg-hissa-border"></div>
            </article>
        </template>
        </div>
        <p v-if="error" role="status" class="border-t border-hissa-border bg-hissa-surface-subtle px-4 py-2.5 text-sm text-hissa-secondary sm:px-5">Summary unavailable. The filing list remains available for inspection.</p>
    </section>
</template>
