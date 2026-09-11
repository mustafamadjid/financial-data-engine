<script setup lang="ts">
import { computed, defineAsyncComponent, nextTick, ref } from 'vue';

import StatusBadge from '../../components/ops/StatusBadge.vue';
import PipelineFilters from '../../features/pipeline/components/PipelineFilters.vue';
import PipelinePagination from '../../features/pipeline/components/PipelinePagination.vue';
import PipelineSummary from '../../features/pipeline/components/PipelineSummary.vue';
import PipelineTable from '../../features/pipeline/components/PipelineTable.vue';
import { usePipelineFilters } from '../../features/pipeline/composables/usePipelineFilters';
import { usePipelineOverviewQueries } from '../../features/pipeline/composables/usePipelineListQuery';
import OpsLayout from '../../layouts/OpsLayout.vue';

defineOptions({ name: 'PipelineIndex' });

const filters = usePipelineFilters({
    search: typeof window === 'undefined' ? '' : window.location.search,
    onUrlChange: (query) => {
        if (typeof window !== 'undefined') window.history.replaceState(window.history.state, '', `${window.location.pathname}${query}`);
    },
});
const { list, summary, hasActiveWork } = usePipelineOverviewQueries(filters.params);
const rows = computed(() => list.data.value?.data ?? []);
const pagination = computed(() => list.data.value?.meta);
const hasActiveFilters = computed(() => {
    const params = filters.params.value;
    return params.search !== undefined || params.processingStage !== undefined || params.qualityStatus !== undefined || params.period !== undefined || params.sort !== 'last_processed_desc' || params.perPage !== 25;
});
const listError = computed(() => (list.error.value instanceof Error ? list.error.value : null));
const selectedFilingId = ref<string | null>(null);
const openHistory = ref(false);
const returnFocus = ref<HTMLElement | null>(null);
const retryTarget = ref<{ filingId: string; jobRunId: number; stage: string } | null>(null);
const FilingDetailDrawer = defineAsyncComponent(() => import('../../features/pipeline/components/FilingDetailDrawer.vue'));
const RetryDialog = defineAsyncComponent(() => import('../../features/pipeline/components/RetryDialog.vue'));

function openFiling(filingId: string, history = false): void {
    returnFocus.value = typeof document === 'undefined' || !(document.activeElement instanceof HTMLElement) ? null : document.activeElement;
    selectedFilingId.value = filingId;
    openHistory.value = history;
}

function openRetry(filingId: string, jobRunId: number, stage: string): void {
    retryTarget.value = { filingId, jobRunId, stage };
}

function closeRetry(): void {
    retryTarget.value = null;
}

async function closeFiling(): Promise<void> {
    selectedFilingId.value = null;
    openHistory.value = false;
    await nextTick();
    returnFocus.value?.focus();
    returnFocus.value = null;
}
</script>

<template>
    <OpsLayout current-path="/ops/pipeline" page-id="pipeline" title="Filings & Pipeline" description="Monitor every filing, identify blockers, and recover the right stage.">
        <template #status>
            <StatusBadge v-show="list.isFetching.value" label="Refreshing pipeline data…" tone="info" aria-live="polite" />
            <StatusBadge v-show="!list.isFetching.value" :label="hasActiveWork ? 'Active monitoring' : 'Every minute'" :tone="hasActiveWork ? 'success' : 'neutral'" />
        </template>

        <PipelineSummary :summary="summary.data.value" :is-loading="summary.isPending.value" />

        <section class="rounded-xl border border-hissa-border bg-hissa-surface p-4 sm:p-6">
            <PipelineFilters
                :search="filters.searchInput.value"
                :processing-stage="filters.processingStage.value"
                :quality-status="filters.qualityStatus.value"
                :period="filters.period.value"
                :sort="filters.sort.value"
                :per-page="filters.perPage.value"
                @update:search="filters.searchInput.value = $event"
                @update:processing-stage="filters.processingStage.value = $event"
                @update:quality-status="filters.qualityStatus.value = $event"
                @update:period="filters.period.value = $event"
                @update:sort="filters.sort.value = $event"
                @update:per-page="filters.perPage.value = $event"
            />
        </section>

        <section aria-labelledby="pipeline-table-heading" class="overflow-hidden rounded-xl border border-hissa-border bg-hissa-surface">
            <div class="flex items-center justify-between gap-4 border-b border-hissa-border px-4 py-4 sm:px-6">
                <div>
                    <h2 id="pipeline-table-heading" class="text-lg font-semibold leading-[26px]">Pipeline filings</h2>
                    <p class="mt-1 text-sm text-hissa-secondary">Server-filtered results with stable filing identities.</p>
                </div>
                <button v-if="list.isError.value" type="button" class="min-h-10 rounded-lg bg-hissa-action px-3 py-2 text-sm font-semibold text-white outline-none focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="list.refetch()">Try again</button>
            </div>

            <div v-if="list.isPending.value" aria-busy="true" aria-label="Loading pipeline filings" class="space-y-3 p-6">
                <div v-for="placeholder in 5" :key="placeholder" class="h-14 animate-pulse rounded-lg bg-hissa-subtle" />
            </div>
            <div v-else-if="list.isError.value" class="p-6" role="alert">
                <h3 class="font-semibold text-hissa-primary">Pipeline data could not be loaded.</h3>
                <p class="mt-1 text-sm text-hissa-secondary">{{ listError?.message ?? 'Check your connection and try again.' }}</p>
            </div>
            <template v-else>
                <PipelineTable :rows="rows" :has-active-filters="hasActiveFilters" @view-detail="openFiling($event)" @view-history="openFiling($event, true)" @retry="openRetry" />
                <PipelinePagination v-if="pagination !== undefined" :current-page="pagination.currentPage" :last-page="pagination.lastPage" :total="pagination.total" @change-page="filters.setPage" />
            </template>
        </section>

        <FilingDetailDrawer v-if="selectedFilingId !== null" :filing-id="selectedFilingId" :initial-history-open="openHistory" @close="closeFiling" />
        <RetryDialog v-if="retryTarget !== null" v-bind="retryTarget" @close="closeRetry" @accepted="closeRetry" />
    </OpsLayout>
</template>
