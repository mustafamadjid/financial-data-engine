<script setup lang="ts">
import { computed, defineAsyncComponent, ref } from 'vue';

import AsyncState from '../../components/ops/AsyncState.vue';
import DataTable from '../../components/ops/DataTable.vue';
import StatusBadge from '../../components/ops/StatusBadge.vue';
import OpsLayout from '../../layouts/OpsLayout.vue';
import { useDebouncedValue } from '../../features/ops/composables/useDebouncedValue';
import { useCanonicalOptionsQuery, useConceptMappingHistoryQuery, useConceptMappingImpactQuery, useConceptMappingInventoryQuery } from '../../features/concept-mapping/queries/useConceptMappingQuery';
import { useCreateMappingVersion } from '../../features/concept-mapping/queries/useCreateMappingVersion';
import { useReprocessAffectedFilings } from '../../features/concept-mapping/queries/useReprocessAffectedFilings';
import type { CreateMappingVersionPayload, MappingDisplayStatus, MappingInventoryItem, MappingListParams } from '../../features/concept-mapping/types/conceptMapping';

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

const params = computed<MappingListParams>(() => ({ page: page.value, perPage: perPage.value, search: search.value.trim() || undefined, status: status.value || undefined, entryPoint: entryPoint.value.trim() || undefined }));
const inventory = useConceptMappingInventoryQuery(params);
const history = useConceptMappingHistoryQuery(selectedSeriesKey);
const selectedVersion = computed(() => history.data.value?.data[0]?.version ?? null);
const impact = useConceptMappingImpactQuery(selectedSeriesKey, selectedVersion);
const canonicalOptions = useCanonicalOptionsQuery();
const createVersion = useCreateMappingVersion(() => selectedSeriesKey.value);
const reprocess = useReprocessAffectedFilings(() => selectedSeriesKey.value);
const rows = computed(() => inventory.data.value?.data ?? []);
const pagination = computed(() => inventory.data.value?.meta);
const selected = computed<MappingInventoryItem | null>(() => rows.value.find((row) => row.mappingSeriesKey === selectedSeriesKey.value) ?? null);
const errorMessage = computed(() => inventory.error.value instanceof Error ? inventory.error.value.message : 'Check your connection and try again.');
const mutationError = computed(() => createVersion.error.value instanceof Error ? createVersion.error.value.message : null);
const reprocessError = computed(() => reprocess.error.value instanceof Error ? reprocess.error.value.message : null);

function applyFilter(): void { page.value = 1; }
function selectRow(row: MappingInventoryItem): void { selectedSeriesKey.value = row.mappingSeriesKey; panel.value = null; }
function openPanel(kind: 'history' | 'impact'): void { panel.value = kind; }
function closePanel(): void { panel.value = null; }
function openMutation(): void { createVersion.reset(); mutationOpen.value = true; }
function closeMutation(): void { if (!createVersion.isPending.value) mutationOpen.value = false; }
function saveVersion(payload: CreateMappingVersionPayload): void { createVersion.mutate(payload, { onSuccess: () => { mutationOpen.value = false; } }); }
function openReprocess(): void { reprocess.reset(); if (impact.data.value?.allowedActions.reprocess.allowed) reprocessOpen.value = true; }
function closeReprocess(): void { if (!reprocess.isPending.value) reprocessOpen.value = false; }
function confirmReprocess(reason: string): void {
    const preview = impact.data.value;
    if (preview === undefined) return;
    reprocess.mutate({ filingIds: preview.filings.map((filing) => filing.filingId), expectedMappingSetVersion: preview.mappingSetVersion, reason }, { onSuccess: () => { reprocessOpen.value = false; } });
}
function statusTone(value: string): 'neutral' | 'success' | 'warning' | 'danger' { if (value === 'APPROVED') return 'success'; if (value === 'REVIEW_REQUIRED' || value === 'DRAFT') return 'warning'; if (value === 'REJECTED') return 'danger'; return 'neutral'; }
</script>

<template>
    <OpsLayout page-id="concept-mapping" title="Concept Mapping" description="Inspect source concepts, mapping versions, and server-computed impact.">
        <template #status><StatusBadge :label="inventory.isFetching.value ? 'Refreshing' : 'Operational'" :tone="inventory.isFetching.value ? 'info' : 'neutral'" /></template>
        <section class="rounded-xl border border-hissa-border bg-hissa-surface p-4 sm:p-5" aria-labelledby="mapping-filters-title"><div class="flex flex-col gap-4 lg:flex-row lg:items-end"><div class="min-w-0 flex-1"><label id="mapping-filters-title" for="mapping-search" class="text-sm font-semibold">Search source or canonical concept</label><input id="mapping-search" v-model="search.input.value" type="search" placeholder="Source concept, canonical code, or name" class="mt-1 min-h-10 w-full rounded-lg border border-hissa-border bg-hissa-surface px-3 text-sm outline-none focus:border-hissa-action focus:ring-2 focus:ring-hissa-action/20" @change="applyFilter"></div><label class="text-sm font-medium lg:w-48">Status<select v-model="status" class="mt-1 min-h-10 w-full rounded-lg border border-hissa-border bg-hissa-surface px-3 text-sm" @change="applyFilter"><option value="">All statuses</option><option value="UNMAPPED">Unmapped</option><option value="DRAFT">Draft</option><option value="APPROVED">Approved</option><option value="REVIEW_REQUIRED">Review required</option><option value="REJECTED">Rejected</option></select></label><label class="text-sm font-medium lg:w-52">Entry point<input v-model="entryPoint" type="search" placeholder="All entry points" class="mt-1 min-h-10 w-full rounded-lg border border-hissa-border bg-hissa-surface px-3 py-2 text-sm" @change="applyFilter"></label></div></section>
        <AsyncState v-if="inventory.isPending.value" state="loading" title="Loading concept mappings" message="Building the mapping inventory from the server dataset." />
        <AsyncState v-else-if="inventory.isError.value" state="error" title="Concept mappings could not be loaded" :message="errorMessage"><template #action><button type="button" class="rounded-lg bg-hissa-action px-3 py-2 text-sm font-semibold text-white" @click="inventory.refetch()">Try again</button></template></AsyncState>
        <AsyncState v-else-if="rows.length === 0" state="empty" title="No mapping series found" message="Try changing the source concept, status, or entry-point filters." />
        <section v-else class="overflow-hidden rounded-xl border border-hissa-border bg-hissa-surface" aria-labelledby="mapping-table-title"><div class="flex items-center justify-between border-b border-hissa-border px-4 py-4 sm:px-6"><div><h2 id="mapping-table-title" class="text-lg font-semibold">Mapping inventory</h2><p class="mt-1 text-sm text-hissa-secondary">{{ pagination?.total ?? rows.length }} source series; unmapped is a server projection.</p></div><span v-if="inventory.isFetching.value" class="text-sm text-hissa-secondary" aria-live="polite">Refreshing...</span></div><DataTable min-width-class="min-w-[1040px]"><template #caption>Concept mapping inventory</template><thead class="bg-hissa-subtle text-xs uppercase tracking-wide text-hissa-secondary"><tr><th class="px-4 py-3">Source concept</th><th class="px-4 py-3">Entry point</th><th class="px-4 py-3">Canonical concept</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Version</th><th class="px-4 py-3 text-right">Affected filings</th><th class="px-4 py-3 text-right">Actions</th></tr></thead><tbody><tr v-for="row in rows" :key="row.mappingSeriesKey" class="border-t border-hissa-border align-top" :class="selectedSeriesKey === row.mappingSeriesKey ? 'bg-hissa-secondary-soft/60' : undefined"><td class="px-4 py-4"><button type="button" class="text-left font-semibold text-hissa-action hover:underline" @click="selectRow(row)">{{ row.sourceConcept }}</button><p class="mt-1 max-w-[270px] truncate font-mono text-[11px] text-hissa-secondary" :title="row.mappingSeriesKey">{{ row.mappingSeriesKey }}</p></td><td class="px-4 py-4 text-sm">{{ row.entryPoint ?? 'Any / unknown' }}</td><td class="px-4 py-4"><template v-if="row.currentMapping"><p class="font-medium">{{ row.currentMapping.canonicalConcept.name }}</p><p class="text-xs text-hissa-secondary">{{ row.currentMapping.canonicalConcept.code }}</p></template><span v-else class="text-sm text-hissa-secondary">No applicable mapping</span></td><td class="px-4 py-4"><StatusBadge :label="row.displayStatus" :tone="statusTone(row.displayStatus)" /></td><td class="px-4 py-4">{{ row.currentMapping ? `v${row.currentMapping.version}` : 'Unknown' }}</td><td class="px-4 py-4 text-right font-semibold">{{ row.affectedFilingCount }}</td><td class="px-4 py-4 text-right"><button type="button" class="rounded-lg border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-action hover:bg-hissa-secondary-soft" @click="selectRow(row)">Inspect</button></td></tr></tbody></DataTable><div v-if="pagination && pagination.lastPage > 1" class="flex items-center justify-between border-t border-hissa-border px-4 py-3 text-sm"><span class="text-hissa-secondary">Page {{ pagination.currentPage }} of {{ pagination.lastPage }}</span><div class="flex gap-2"><button type="button" class="rounded-lg border border-hissa-border px-3 py-2 disabled:opacity-50" :disabled="page <= 1" @click="page--">Previous</button><button type="button" class="rounded-lg border border-hissa-border px-3 py-2 disabled:opacity-50" :disabled="page >= pagination.lastPage" @click="page++">Next</button></div></div></section>
        <section v-if="selected" class="rounded-xl border border-hissa-border bg-hissa-surface p-5" aria-labelledby="selected-mapping-title"><div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"><div><p class="text-xs uppercase tracking-wide text-hissa-secondary">Selected series</p><h2 id="selected-mapping-title" class="mt-1 text-xl font-semibold">{{ selected.sourceConcept }}</h2><p class="mt-1 text-sm text-hissa-secondary">{{ selected.entryPoint ?? 'Any / unknown entry point' }}; {{ selected.affectedFilingCount }} affected filings</p></div><div class="flex flex-wrap gap-2"><button type="button" class="rounded-lg border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-action" @click="openPanel('history')">View history</button><button type="button" class="rounded-lg border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-action" :disabled="!selected.allowedActions.previewImpact.allowed" @click="openPanel('impact')">Preview impact</button><button type="button" class="rounded-lg border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-action" @click="openMutation">Save version</button><button type="button" class="rounded-lg border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-action disabled:cursor-not-allowed disabled:opacity-50" :disabled="!selected.allowedActions.reprocess.allowed || !impact.data.value" @click="openReprocess">Reprocess</button></div></div><div class="mt-5 grid gap-4 md:grid-cols-3"><div class="rounded-lg bg-hissa-subtle p-4"><p class="text-xs uppercase tracking-wide text-hissa-secondary">Current mapping</p><p class="mt-1 font-semibold">{{ selected.currentMapping?.canonicalConcept.name ?? 'UNMAPPED' }}</p><p class="text-xs text-hissa-secondary">{{ selected.currentMapping?.canonicalConcept.code ?? 'No persisted mapping row' }}</p></div><div class="rounded-lg bg-hissa-subtle p-4"><p class="text-xs uppercase tracking-wide text-hissa-secondary">Rationale</p><p class="mt-1 text-sm">{{ selected.currentMapping?.rationale ?? 'No rationale recorded.' }}</p></div><div class="rounded-lg bg-hissa-subtle p-4"><p class="text-xs uppercase tracking-wide text-hissa-secondary">Evidence navigation</p><a class="mt-1 inline-block text-sm font-semibold text-hissa-action hover:underline" :href="`/ops/financial-review?search=${encodeURIComponent(selected.sourceConcept)}`">Open source facts</a><p class="mt-1 text-xs text-hissa-secondary">Inspect source facts without changing mapping history.</p></div></div></section>
        <HistoryPanel v-if="panel === 'history' && selectedSeriesKey !== null" :series-key="selectedSeriesKey" :data="history.data.value" :loading="history.isPending.value" :error="history.error.value instanceof Error ? history.error.value.message : null" @close="closePanel" @retry="history.refetch" />
        <ImpactPanel v-if="panel === 'impact' && selectedSeriesKey !== null" :data="impact.data.value" :loading="impact.isPending.value" :error="impact.error.value instanceof Error ? impact.error.value.message : null" @close="closePanel" @retry="impact.refetch" />
        <CreateMappingVersionDialog v-if="mutationOpen && selected" :selected="selected" :canonical-options="canonicalOptions.data.value ?? []" :submitting="createVersion.isPending.value" :error="mutationError" @close="closeMutation" @submit="saveVersion" />
        <ReprocessConfirmationDialog v-if="reprocessOpen && impact.data.value" :data="impact.data.value" :submitting="reprocess.isPending.value" :error="reprocessError" @close="closeReprocess" @submit="confirmReprocess" />
    </OpsLayout>
</template>
