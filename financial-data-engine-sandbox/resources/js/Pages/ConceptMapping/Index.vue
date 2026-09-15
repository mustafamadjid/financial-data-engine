<script setup lang="ts">
import { computed, defineAsyncComponent, ref, watch } from 'vue';

import AsyncState from '../../components/ops/AsyncState.vue';
import DataTable from '../../components/ops/DataTable.vue';
import FilterBar from '../../components/ops/FilterBar.vue';
import Pagination from '../../components/ops/Pagination.vue';
import SearchInput from '../../components/ops/SearchInput.vue';
import StatusBadge, { type StatusBadgeTone } from '../../components/ops/StatusBadge.vue';
import Toolbar from '../../components/ops/Toolbar.vue';
import OpsLayout from '../../layouts/OpsLayout.vue';
import { displayValue, humanize } from '../../features/ops/utils/formatters';
import { useDebouncedValue } from '../../features/ops/composables/useDebouncedValue';
import { useCanonicalOptionsQuery, useConceptMappingHistoryQuery, useConceptMappingImpactQuery, useConceptMappingInventoryQuery } from '../../features/concept-mapping/queries/useConceptMappingQuery';
import { useCreateMappingVersion } from '../../features/concept-mapping/queries/useCreateMappingVersion';
import { useReprocessAffectedFilings } from '../../features/concept-mapping/queries/useReprocessAffectedFilings';
import type { CreateMappingVersionPayload, MappingDisplayStatus, MappingInventoryItem, MappingListParams } from '../../features/concept-mapping/types/conceptMapping';

defineOptions({ name: 'ConceptMappingIndex' });

const HistoryPanel = defineAsyncComponent(() => import('../../features/concept-mapping/components/MappingHistoryPanel.vue'));
const ImpactPanel = defineAsyncComponent(() => import('../../features/concept-mapping/components/MappingImpactPanel.vue'));
const CreateMappingVersionDialog = defineAsyncComponent(() => import('../../features/concept-mapping/components/CreateMappingVersionDialog.vue'));
const ReprocessConfirmationDialog = defineAsyncComponent(() => import('../../features/concept-mapping/components/ReprocessConfirmationDialog.vue'));

const queryParams = new URLSearchParams(typeof window === 'undefined' ? '' : window.location.search);
const search = useDebouncedValue(queryParams.get('search') ?? '', 300);
const status = ref<MappingDisplayStatus | ''>((queryParams.get('status') as MappingDisplayStatus | null) ?? '');
const entryPoint = ref(queryParams.get('entry_point') ?? '');
const page = ref(Number(queryParams.get('page') ?? 1));
const perPage = ref<25 | 50 | 100>(25);
const selectedSeriesKey = ref<string | null>(null);
const panel = ref<'history' | 'impact' | null>(null);
const mutationOpen = ref(false);
const reprocessOpen = ref(false);
const mappingNotice = ref<string | null>(null);

const params = computed<MappingListParams>(() => ({ page: page.value, perPage: perPage.value, search: search.value.trim() || undefined, status: status.value || undefined, entryPoint: entryPoint.value.trim() || undefined }));
const inventory = useConceptMappingInventoryQuery(params);
const history = useConceptMappingHistoryQuery(selectedSeriesKey);
const selectedVersion = computed(() => history.data.value?.data?.[0]?.version ?? null);
const impact = useConceptMappingImpactQuery(selectedSeriesKey, selectedVersion);
const canonicalOptions = useCanonicalOptionsQuery();
const createVersion = useCreateMappingVersion(() => selectedSeriesKey.value);
const reprocess = useReprocessAffectedFilings(() => selectedSeriesKey.value);
const rows = computed(() => inventory.data.value?.data ?? []);
const pagination = computed(() => inventory.data.value?.meta);
const selected = computed<MappingInventoryItem | null>(() => rows.value.find((row) => row.mappingSeriesKey === selectedSeriesKey.value) ?? null);
const activeFilterCount = computed(() => [search.input.value.trim(), status.value, entryPoint.value.trim()].filter((value) => value !== '').length);
const hasActiveFilters = computed(() => activeFilterCount.value > 0);
const resultSummary = computed(() => pagination.value === undefined ? 'Compare source concepts, canonical targets, versions, and impact.' : `Showing ${rows.value.length} of ${pagination.value.total ?? 0} mapping series.`);
const errorMessage = computed(() => inventory.error.value instanceof Error ? inventory.error.value.message : 'Check your connection and try again.');
const mutationError = computed(() => createVersion.error.value instanceof Error ? createVersion.error.value.message : null);
const reprocessError = computed(() => reprocess.error.value instanceof Error ? reprocess.error.value.message : null);

function applyFilter(): void {
    page.value = 1;
}

function syncUrl(): void {
    if (typeof window === 'undefined') return;
    const query = new URLSearchParams();
    if (search.value.trim() !== '') query.set('search', search.value.trim());
    if (status.value !== '') query.set('status', status.value);
    if (entryPoint.value.trim() !== '') query.set('entry_point', entryPoint.value.trim());
    if (page.value > 1) query.set('page', String(page.value));
    const serialized = query.toString();
    window.history.replaceState(window.history.state, '', `${window.location.pathname}${serialized === '' ? '' : `?${serialized}`}`);
}

watch([() => search.value, status, entryPoint, page], syncUrl);

function clearFilters(): void {
    search.input.value = '';
    search.flush();
    status.value = '';
    entryPoint.value = '';
    page.value = 1;
    perPage.value = 25;
}

function selectRow(row: MappingInventoryItem): void {
    if (typeof row.mappingSeriesKey !== 'string' || row.mappingSeriesKey.trim() === '') return;
    selectedSeriesKey.value = row.mappingSeriesKey;
    panel.value = null;
    mappingNotice.value = null;
}

function openPanel(kind: 'history' | 'impact'): void {
    panel.value = kind;
}

function closePanel(): void {
    panel.value = null;
}

function openMutation(): void {
    createVersion.reset();
    mutationOpen.value = true;
}

function closeMutation(): void {
    if (!createVersion.isPending.value) mutationOpen.value = false;
}

function saveVersion(payload: CreateMappingVersionPayload): void {
    if (createVersion.isPending.value) return;
    createVersion.mutate(payload, {
        onSuccess: (created) => {
            mutationOpen.value = false;
            mappingNotice.value = `Mapping version v${displayValue(created.version, 'new')} saved. Reprocessing remains a separate action.`;
        },
    });
}

function openReprocess(): void {
    reprocess.reset();
    if (impact.data.value?.allowedActions?.reprocess?.allowed === true && impact.data.value.mappingSetVersion != null) reprocessOpen.value = true;
}

function closeReprocess(): void {
    if (!reprocess.isPending.value) reprocessOpen.value = false;
}

function confirmReprocess(reason: string): void {
    const preview = impact.data.value;
    if (reprocess.isPending.value || preview === undefined || preview.mappingSetVersion == null || !Array.isArray(preview.filings)) return;
    reprocess.mutate({ filingIds: (preview.filings ?? []).map((filing) => filing.filingId), expectedMappingSetVersion: preview.mappingSetVersion, reason }, {
        onSuccess: (accepted) => {
            reprocessOpen.value = false;
            mappingNotice.value = `Reprocess queued for ${accepted.acceptedCount} affected filing${accepted.acceptedCount === 1 ? '' : 's'}.`;
        },
    });
}

function statusTone(value: string): StatusBadgeTone {
    if (value === 'APPROVED') return 'success';
    if (value === 'REVIEW_REQUIRED' || value === 'DRAFT') return 'warning';
    if (value === 'REJECTED') return 'danger';
    return 'neutral';
}
</script>

<template>
    <OpsLayout current-path="/ops/concept-mappings" page-id="concept-mapping" title="Concept Mapping" description="Inspect source concepts, mapping versions, and server-computed impact.">
        <template #status><StatusBadge :label="inventory.isFetching.value ? 'Refreshing' : 'Operational'" :tone="inventory.isFetching.value ? 'info' : 'neutral'" /></template>

        <FilterBar label="Concept mapping filters" content-class="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(22rem,1.8fr)_minmax(12rem,1fr)_minmax(14rem,1fr)]">
            <SearchInput id="mapping-search" v-model="search.input.value" label="Search concepts" placeholder="Source concept, canonical code, or name" input-class="w-full" @change="applyFilter" />
            <label for="mapping-status" class="grid min-w-0 gap-1.5 text-[13px] font-semibold leading-[18px] text-hissa-secondary">
                <span>Status</span>
                <select id="mapping-status" v-model="status" class="min-h-10 w-full min-w-0 rounded-md border border-hissa-border bg-hissa-surface px-3 text-sm font-normal text-hissa-primary outline-none transition-colors hover:border-hissa-border-strong focus-visible:border-hissa-action focus-visible:ring-2 focus-visible:ring-hissa-action/20" @change="applyFilter">
                    <option value="">All statuses</option>
                    <option value="UNMAPPED">Unmapped</option>
                    <option value="DRAFT">Draft</option>
                    <option value="APPROVED">Approved</option>
                    <option value="REVIEW_REQUIRED">Review required</option>
                    <option value="REJECTED">Rejected</option>
                </select>
            </label>
            <label for="mapping-entry-point" class="grid min-w-0 gap-1.5 text-[13px] font-semibold leading-[18px] text-hissa-secondary">
                <span>Entry point</span>
                <input id="mapping-entry-point" v-model="entryPoint" type="search" placeholder="All entry points" class="min-h-10 w-full min-w-0 rounded-md border border-hissa-border bg-hissa-surface px-3 text-sm font-normal text-hissa-primary outline-none placeholder:text-hissa-muted transition-colors hover:border-hissa-border-strong focus-visible:border-hissa-action focus-visible:ring-2 focus-visible:ring-hissa-action/20" @change="applyFilter" />
            </label>
            <template #footer>
                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-hissa-border pt-3">
                    <p class="ops-meta text-hissa-secondary">{{ activeFilterCount }} {{ activeFilterCount === 1 ? 'filter' : 'filters' }} active <span aria-hidden="true">·</span> Mapping versions are append-only.</p>
                    <button v-if="hasActiveFilters" type="button" aria-label="Clear concept mapping filters" class="min-h-10 rounded-md border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-action outline-none transition-colors hover:bg-hissa-secondary-soft focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="clearFilters">Clear filters</button>
                </div>
            </template>
        </FilterBar>

        <div v-if="mappingNotice !== null" role="status" aria-live="polite" class="flex flex-wrap items-center justify-between gap-3 border-l border-hissa-success bg-hissa-success-soft px-3 py-2.5 text-sm">
            <p class="min-w-0 text-hissa-success">{{ mappingNotice }}</p>
            <button type="button" class="min-h-10 shrink-0 rounded-md px-2.5 py-2 font-semibold text-hissa-success outline-none transition-colors hover:bg-hissa-surface focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="mappingNotice = null">Dismiss</button>
        </div>

        <AsyncState v-if="inventory.isPending.value" state="loading" title="Loading concept mappings" message="Building the mapping inventory from the server dataset." />
        <AsyncState v-else-if="inventory.isError.value" state="error" title="Concept mappings could not be loaded" :message="errorMessage">
            <template #action><button type="button" class="min-h-10 rounded-md bg-hissa-action px-3 py-2 text-sm font-semibold text-white outline-none transition-colors hover:bg-hissa-action-hover focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="inventory.refetch()">Try again</button></template>
        </AsyncState>
        <AsyncState v-else-if="rows.length === 0" state="empty" title="No mapping series found" message="Try changing the concept, status, or entry-point filters.">
            <template #action><button v-if="hasActiveFilters" type="button" class="min-h-10 rounded-md border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-action outline-none transition-colors hover:bg-hissa-secondary-soft focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="clearFilters">Clear filters</button></template>
        </AsyncState>
        <section v-else class="overflow-hidden rounded-lg border border-hissa-border bg-hissa-surface" aria-labelledby="mapping-table-title">
            <Toolbar>
                <template #title><h2 id="mapping-table-title" class="ops-section-title">Mapping inventory</h2></template>
                <template #description><p class="mt-1 text-sm text-hissa-secondary">{{ resultSummary }} Unmapped is a server projection.</p></template>
                <template #actions><span v-if="inventory.isFetching.value" class="ops-meta text-hissa-secondary" aria-live="polite">Refreshing…</span></template>
            </Toolbar>
            <DataTable min-width-class="min-w-[1120px]">
                <template #caption>Concept mapping inventory</template>
                <thead class="sticky top-0 z-10">
                    <tr>
                        <th scope="col" class="w-64 whitespace-nowrap px-3 py-2.5">Source concept / key</th>
                        <th scope="col" class="w-64 whitespace-nowrap px-3 py-2.5">Current canonical target</th>
                        <th scope="col" class="w-24 whitespace-nowrap px-3 py-2.5">Version</th>
                        <th scope="col" class="w-36 whitespace-nowrap px-3 py-2.5">Status</th>
                        <th scope="col" class="w-36 whitespace-nowrap px-3 py-2.5 text-right">Affected filings</th>
                        <th scope="col" class="w-24 whitespace-nowrap px-3 py-2.5 text-right"><span class="sr-only">Action</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hissa-border">
                    <tr v-for="row in rows" :key="row.mappingSeriesKey" :data-selected="selectedSeriesKey === row.mappingSeriesKey ? 'true' : undefined" class="group bg-hissa-surface align-top hover:bg-hissa-surface-subtle" :class="selectedSeriesKey === row.mappingSeriesKey ? 'bg-hissa-secondary-soft/60' : undefined">
                        <td class="px-3 py-3">
                            <button type="button" class="ops-wrap min-h-10 max-w-full rounded-md text-left font-semibold text-hissa-action outline-none transition-colors hover:text-hissa-action-hover hover:underline focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" :aria-label="`Inspect mapping for ${displayValue(row.sourceConcept, 'Unknown source concept')}`" @click="selectRow(row)">{{ displayValue(row.sourceConcept, 'Unknown source concept') }}</button>
                            <p class="ops-technical ops-wrap mt-1 max-w-[16rem] truncate text-hissa-secondary" :title="displayValue(row.mappingSeriesKey)">{{ displayValue(row.mappingSeriesKey) }}</p>
                            <p class="ops-wrap mt-1 ops-meta text-hissa-secondary">{{ displayValue(row.entryPoint, 'Any / unknown entry point') }}</p>
                        </td>
                        <td class="px-3 py-3">
                            <template v-if="row.currentMapping">
                                <p class="ops-wrap font-medium text-hissa-primary">{{ displayValue(row.currentMapping?.canonicalConcept?.name, 'Canonical concept unavailable') }}</p>
                                <p class="ops-technical ops-wrap mt-1 text-hissa-secondary">{{ displayValue(row.currentMapping?.canonicalConcept?.code) }}</p>
                            </template>
                            <span v-else class="text-sm text-hissa-secondary">No applicable mapping</span>
                        </td>
                        <td class="px-3 py-3"><span class="ops-numeric text-hissa-primary">{{ row.currentMapping ? `v${row.currentMapping.version}` : '—' }}</span></td>
                        <td class="px-3 py-3"><StatusBadge :label="humanize(row.displayStatus)" :tone="statusTone(row.displayStatus)" /></td>
                        <td class="ops-numeric px-3 py-3 text-right font-semibold text-hissa-primary">{{ row.affectedFilingCount ?? 0 }}</td>
                        <td class="px-3 py-3 text-right"><button type="button" class="min-h-10 whitespace-nowrap rounded-md border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-action outline-none transition-colors hover:bg-hissa-secondary-soft focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" :aria-label="`Inspect mapping for ${displayValue(row.sourceConcept, 'Unknown source concept')}`" @click="selectRow(row)">Inspect</button></td>
                    </tr>
                </tbody>
            </DataTable>
            <Pagination v-if="pagination !== undefined" aria-label="Concept mapping pagination" total-label="source series" :current-page="pagination.currentPage ?? 1" :last-page="pagination.lastPage ?? 1" :per-page="pagination.perPage ?? perPage" :total="pagination.total ?? rows.length" @change-page="page = $event" />
        </section>

        <section v-if="selected" class="overflow-hidden rounded-lg border border-hissa-border bg-hissa-surface" aria-labelledby="selected-mapping-title">
            <header class="flex flex-wrap items-start justify-between gap-4 border-b border-hissa-border px-4 py-4 sm:px-5">
                <div class="min-w-0">
                    <h2 id="selected-mapping-title" class="ops-section-title">Selected mapping</h2>
                    <p class="ops-technical ops-wrap mt-1 max-w-full text-hissa-secondary" :title="displayValue(selected.mappingSeriesKey)">{{ displayValue(selected.mappingSeriesKey) }}</p>
                </div>
                <div class="flex w-full min-w-0 flex-wrap items-center gap-2 sm:w-auto sm:justify-end">
                    <button type="button" class="min-h-10 rounded-md border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-action outline-none transition-colors hover:bg-hissa-secondary-soft focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="openPanel('history')">View history</button>
                    <button type="button" class="min-h-10 rounded-md border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-action outline-none transition-colors hover:bg-hissa-secondary-soft focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60" :disabled="selected.allowedActions?.previewImpact?.allowed !== true" :title="selected.allowedActions?.previewImpact?.reason ?? undefined" @click="openPanel('impact')">Preview impact</button>
                    <button type="button" class="min-h-10 rounded-md bg-hissa-action px-3 py-2 text-sm font-semibold text-white outline-none transition-colors hover:bg-hissa-action-hover focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60" :disabled="selected.allowedActions?.edit?.allowed !== true" :title="selected.allowedActions?.edit?.reason ?? undefined" @click="openMutation">Save version</button>
                    <button type="button" class="min-h-10 rounded-md border border-hissa-danger/40 px-3 py-2 text-sm font-semibold text-hissa-danger outline-none transition-colors hover:bg-hissa-danger-soft focus-visible:ring-2 focus-visible:ring-hissa-danger focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60" :disabled="selected.allowedActions?.reprocess?.allowed !== true || impact.data.value?.mappingSetVersion == null" :title="selected.allowedActions?.reprocess?.allowed !== true ? selected.allowedActions?.reprocess?.reason ?? undefined : impact.data.value?.mappingSetVersion == null ? 'Impact preview is incomplete.' : undefined" @click="openReprocess">Reprocess filings</button>
                    <div v-if="selected.allowedActions?.previewImpact?.allowed !== true || selected.allowedActions?.edit?.allowed !== true || selected.allowedActions?.reprocess?.allowed !== true || impact.data.value?.mappingSetVersion == null" class="basis-full grid gap-1 text-left sm:text-right">
                        <p v-if="selected.allowedActions?.previewImpact?.allowed !== true" class="ops-wrap ops-meta text-hissa-muted">Preview impact unavailable: {{ selected.allowedActions?.previewImpact?.reason ?? 'Capability not returned.' }}</p>
                        <p v-if="selected.allowedActions?.edit?.allowed !== true" class="ops-wrap ops-meta text-hissa-muted">Save version unavailable: {{ selected.allowedActions?.edit?.reason ?? 'Capability not returned.' }}</p>
                        <p v-if="selected.allowedActions?.reprocess?.allowed !== true || impact.data.value?.mappingSetVersion == null" class="ops-wrap ops-meta text-hissa-muted">Reprocess unavailable: {{ selected.allowedActions?.reprocess?.allowed !== true ? selected.allowedActions?.reprocess?.reason ?? 'Capability not returned.' : 'Impact preview is incomplete.' }}</p>
                    </div>
                </div>
            </header>

            <div class="grid gap-0 border-b border-hissa-border lg:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]">
                <div class="min-w-0 px-4 py-4 sm:px-5">
                    <p class="ops-meta text-hissa-secondary">Source concept</p>
                    <p class="ops-wrap mt-1 font-semibold text-hissa-primary">{{ displayValue(selected.sourceConcept, 'Unknown source concept') }}</p>
                    <p class="ops-wrap mt-1 ops-meta text-hissa-secondary">{{ displayValue(selected.entryPoint, 'Any / unknown entry point') }}</p>
                </div>
                <div class="flex items-center border-y border-hissa-border px-4 py-2 text-center text-sm font-semibold text-hissa-secondary lg:border-y-0 lg:border-x">maps to</div>
                <div class="min-w-0 px-4 py-4 sm:px-5">
                    <p class="ops-meta text-hissa-secondary">Canonical target</p>
                    <p class="ops-wrap mt-1 font-semibold text-hissa-primary">{{ displayValue(selected.currentMapping?.canonicalConcept?.name, 'UNMAPPED') }}</p>
                    <p class="ops-technical ops-wrap mt-1 text-hissa-secondary">{{ displayValue(selected.currentMapping?.canonicalConcept?.code, 'No persisted mapping row') }}</p>
                </div>
            </div>

            <dl class="grid gap-x-5 gap-y-4 px-4 py-4 text-sm sm:grid-cols-2 lg:grid-cols-4 sm:px-5">
                <div><dt class="ops-meta text-hissa-secondary">Current version</dt><dd class="ops-numeric mt-0.5 font-semibold text-hissa-primary">{{ selected.currentMapping ? `v${selected.currentMapping.version}` : 'No version' }}</dd></div>
                <div><dt class="ops-meta text-hissa-secondary">Status</dt><dd class="mt-1"><StatusBadge :label="humanize(selected.displayStatus)" :tone="statusTone(selected.displayStatus)" /></dd></div>
                <div><dt class="ops-meta text-hissa-secondary">Affected filings</dt><dd class="ops-numeric mt-0.5 font-semibold text-hissa-primary">{{ selected.affectedFilingCount }}</dd></div>
                <div><dt class="ops-meta text-hissa-secondary">Rationale</dt><dd class="mt-0.5 text-hissa-primary">{{ selected.currentMapping?.rationale ?? 'No rationale recorded.' }}</dd></div>
            </dl>
        </section>

        <HistoryPanel v-if="panel === 'history' && selectedSeriesKey !== null" :series-key="selectedSeriesKey" :data="history.data.value" :loading="history.isPending.value" :error="history.error.value instanceof Error ? history.error.value.message : null" @close="closePanel" @retry="history.refetch()" />
        <ImpactPanel v-if="panel === 'impact' && selectedSeriesKey !== null" :data="impact.data.value" :loading="impact.isPending.value" :error="impact.error.value instanceof Error ? impact.error.value.message : null" @close="closePanel" @retry="impact.refetch()" />
        <CreateMappingVersionDialog v-if="mutationOpen && selected" :selected="selected" :canonical-options="canonicalOptions.data.value ?? []" :submitting="createVersion.isPending.value" :error="mutationError" @close="closeMutation" @submit="saveVersion" />
        <ReprocessConfirmationDialog v-if="reprocessOpen && impact.data.value" :data="impact.data.value" :submitting="reprocess.isPending.value" :error="reprocessError" @close="closeReprocess" @submit="confirmReprocess" />
    </OpsLayout>
</template>
