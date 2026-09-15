<script setup lang="ts">
import { computed, defineAsyncComponent, nextTick, ref } from 'vue';

import AsyncState from '../../components/ops/AsyncState.vue';
import FilterBar from '../../components/ops/FilterBar.vue';
import Pagination from '../../components/ops/Pagination.vue';
import StatusBadge from '../../components/ops/StatusBadge.vue';
import Toolbar from '../../components/ops/Toolbar.vue';
import PipelineFilters from '../../features/pipeline/components/PipelineFilters.vue';
import RetryDialog from '../../features/pipeline/components/RetryDialog.vue';
import PipelineSummary from '../../features/pipeline/components/PipelineSummary.vue';
import PipelineTable from '../../features/pipeline/components/PipelineTable.vue';
import { usePipelineFilters } from '../../features/pipeline/composables/usePipelineFilters';
import { usePipelineOverviewQueries } from '../../features/pipeline/composables/usePipelineListQuery';
import type { PipelineFilingListItem } from '../../features/pipeline/types/pipeline';
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
const selectedFilingId = ref<string | null>(null);
const selectedFiling = computed<PipelineFilingListItem | undefined>(() => rows.value.find((row) => row.filingId === selectedFilingId.value));
const hasActiveFilters = computed(() => {
    const params = filters.params.value;
    return params.search !== undefined || params.processingStage !== undefined || params.qualityStatus !== undefined || params.period !== undefined || params.sort !== 'last_processed_desc' || params.perPage !== 25;
});
const activeFilterCount = computed(() => [filters.searchInput.value.trim(), filters.processingStage.value, filters.qualityStatus.value, filters.period.value?.trim()].filter((value) => value !== undefined && value !== '').length);
const resultSummary = computed(() => pagination.value === undefined ? 'Scan stage progress, quality status, and recovery actions.' : `Showing ${rows.value.length} of ${pagination.value.total ?? 0} filings.`);
const openHistory = ref(false);
const returnFocus = ref<HTMLElement | null>(null);
const retryTarget = ref<{ filingId: string; jobRunId: number; stage: string } | null>(null);
const pipelineNotice = ref<string | null>(null);
const FilingDetailDrawer = defineAsyncComponent(() => import('../../features/pipeline/components/FilingDetailDrawer.vue'));

function openFiling(filingId: string, history = false): void {
    returnFocus.value = typeof document === 'undefined' || !(document.activeElement instanceof HTMLElement) ? null : document.activeElement;
    selectedFilingId.value = filingId;
    openHistory.value = history;
}

function clearFilters(): void {
    filters.searchInput.value = '';
    filters.processingStage.value = undefined;
    filters.qualityStatus.value = undefined;
    filters.period.value = undefined;
    filters.sort.value = 'last_processed_desc';
    filters.perPage.value = 25;
    filters.page.value = 1;
}

function openRetry(filingId: string, jobRunId: number, stage: string): void {
    retryTarget.value = { filingId, jobRunId, stage };
}

function closeRetry(): void {
    retryTarget.value = null;
}

function handleRetryAccepted(): void {
    const target = retryTarget.value;
    retryTarget.value = null;
    if (target !== null) pipelineNotice.value = `Retry queued for ${target.filingId} at ${target.stage}.`;
}

function handleFilingAccepted(operation: 'reprocess', filingId: string): void {
    pipelineNotice.value = `${operation === 'reprocess' ? 'Reprocess' : 'Operation'} queued for ${filingId}.`;
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
            <StatusBadge v-if="!list.isFetching.value && hasActiveWork" label="Active monitoring" tone="success" />
        </template>

        <PipelineSummary :summary="summary.data.value" :is-loading="summary.isPending.value" :error="summary.error.value instanceof Error ? summary.error.value.message : null" />

        <FilterBar label="Pipeline filters" content-class="grid gap-3">
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
            <template #footer>
                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-hissa-border pt-3 text-sm">
                    <p class="text-hissa-secondary">{{ activeFilterCount }} {{ activeFilterCount === 1 ? 'filter' : 'filters' }} active</p>
                    <button v-if="hasActiveFilters" type="button" aria-label="Clear pipeline filters" class="min-h-10 rounded-md border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-action outline-none transition-colors hover:bg-hissa-secondary-soft focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="clearFilters">Clear filters</button>
                </div>
            </template>
        </FilterBar>

        <div v-if="pipelineNotice !== null" role="status" aria-live="polite" class="flex flex-wrap items-center justify-between gap-3 border-l border-hissa-success bg-hissa-success-soft px-3 py-2.5 text-sm">
            <p class="min-w-0 text-hissa-success">{{ pipelineNotice }}</p>
            <button type="button" class="min-h-10 shrink-0 rounded-md px-2.5 py-2 font-semibold text-hissa-success outline-none transition-colors hover:bg-hissa-surface focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="pipelineNotice = null">Dismiss</button>
        </div>

        <section aria-labelledby="pipeline-table-heading" class="overflow-hidden rounded-lg border border-hissa-border bg-hissa-surface">
            <Toolbar>
                <template #title>
                    <h2 id="pipeline-table-heading" class="ops-section-title">Pipeline filings</h2>
                </template>
                <template #description>
                    <p class="mt-1 text-sm text-hissa-secondary">{{ resultSummary }}</p>
                </template>
            </Toolbar>

            <AsyncState v-if="list.isPending.value" embedded state="loading" title="Loading pipeline filings" message="Fetching current filing stages and quality status." aria-label="Loading pipeline filings" />
            <AsyncState v-else-if="list.isError.value" embedded state="error" title="Pipeline data could not be loaded." :message="list.error.value instanceof Error ? list.error.value.message : 'Check your connection and try again.'">
                <template #action><button type="button" class="min-h-10 rounded-md bg-hissa-action px-3 py-2 text-sm font-semibold text-white outline-none transition-colors hover:bg-hissa-action-hover focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="list.refetch()">Try again</button></template>
            </AsyncState>
            <template v-else>
                <PipelineTable :rows="rows" :has-active-filters="hasActiveFilters" @view-detail="openFiling($event)" @view-history="openFiling($event, true)" @retry="openRetry" />
                <Pagination v-if="pagination !== undefined" aria-label="Pipeline pagination" total-label="filings" :current-page="pagination.currentPage ?? 1" :last-page="pagination.lastPage ?? 1" :per-page="pagination.perPage ?? filters.perPage.value" :total="pagination.total ?? rows.length" @change-page="filters.setPage" />
            </template>
        </section>

        <FilingDetailDrawer
            v-if="selectedFilingId !== null"
            :filing-id="selectedFilingId"
            :initial-history-open="openHistory"
            :initial-reprocess-stage="selectedFiling?.errorSummary?.stage"
            :reprocess-allowed="selectedFiling?.allowedActions?.reprocess?.allowed === true"
            :reprocess-reason="selectedFiling?.allowedActions?.reprocess?.reason ?? undefined"
            @close="closeFiling"
            @accepted="handleFilingAccepted"
        />
        <RetryDialog v-if="retryTarget !== null" v-bind="retryTarget" @close="closeRetry" @accepted="handleRetryAccepted" />
    </OpsLayout>
</template>
