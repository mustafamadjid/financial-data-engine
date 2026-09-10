<script setup lang="ts">
import type { PipelineSummary } from '../types/pipeline';

defineProps<{
    summary?: PipelineSummary;
    isLoading: boolean;
}>();
</script>

<template>
    <section aria-label="Pipeline summary" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article v-for="metric in summary === undefined ? [] : [
            { label: 'Active filings', value: summary.active, tone: 'text-hissa-info' },
            { label: 'Failed executions', value: summary.failedExecutions, tone: 'text-hissa-danger' },
            { label: 'Review required', value: summary.reviewRequired, tone: 'text-hissa-warning' },
            { label: 'Verified', value: summary.verified, tone: 'text-hissa-action' },
        ]" :key="metric.label" class="rounded-xl border border-hissa-border bg-hissa-surface p-4">
            <p class="text-[13px] font-semibold text-hissa-secondary">{{ metric.label }}</p>
            <p class="mt-2 text-2xl font-semibold leading-8" :class="metric.tone">{{ metric.value }}</p>
        </article>
        <template v-if="isLoading && summary === undefined">
            <article v-for="placeholder in 4" :key="placeholder" class="h-25 animate-pulse rounded-xl border border-hissa-border bg-hissa-surface p-4">
                <div class="h-3 w-24 rounded bg-hissa-border"></div>
                <div class="mt-4 h-7 w-12 rounded bg-hissa-border"></div>
            </article>
        </template>
    </section>
</template>
