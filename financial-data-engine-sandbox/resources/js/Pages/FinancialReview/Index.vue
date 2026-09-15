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
import { useFinancialFactDetailQuery, useFinancialFactsQuery } from '../../features/financial-review/queries/useFinancialReviewQuery';
import type { FinancialFactListItem, FinancialFactListParams, FinancialScope, FinancialValidationStatus, NormalizationStatus } from '../../features/financial-review/types/financialReview';

defineOptions({ name: 'FinancialReviewIndex' });

const DetailDialog = defineAsyncComponent(() => import('../../features/financial-review/components/FinancialFactDetailDialog.vue'));
const queryParams = new URLSearchParams(typeof window === 'undefined' ? '' : window.location.search);
const search = useDebouncedValue(queryParams.get('search') ?? '', 300);
const filingId = ref(queryParams.get('filing_id') ?? '');
const scope = ref<FinancialScope | ''>((queryParams.get('scope') as FinancialScope | null) ?? '');
const normalizationStatus = ref<NormalizationStatus | ''>((queryParams.get('normalization_status') as NormalizationStatus | null) ?? '');
const validationStatus = ref<FinancialValidationStatus | ''>((queryParams.get('validation_status') as FinancialValidationStatus | null) ?? '');
const page = ref(Number(queryParams.get('page') ?? 1));
const perPage = ref<25 | 50 | 100>(25);
const selectedFactId = ref<string | null>(null);

const params = computed<FinancialFactListParams>(() => ({
    filingId: filingId.value.trim() || undefined,
    search: search.value.trim() || undefined,
    scope: scope.value || undefined,
    normalizationStatus: normalizationStatus.value || undefined,
    validationStatus: validationStatus.value || undefined,
    page: page.value,
    perPage: perPage.value,
    sort: 'filing_asc',
}));
const facts = useFinancialFactsQuery(params);
const detail = useFinancialFactDetailQuery(selectedFactId);
const rows = computed(() => facts.data.value?.data ?? []);
const pagination = computed(() => facts.data.value?.meta);
const activeFilterCount = computed(() => [search.input.value.trim(), filingId.value.trim(), scope.value, normalizationStatus.value, validationStatus.value].filter((value) => value !== '').length);
const hasActiveFilters = computed(() => activeFilterCount.value > 0);
const resultSummary = computed(() => pagination.value === undefined ? 'Compare values, periods, units, and review status.' : `Showing ${rows.value.length} of ${pagination.value.total ?? 0} financial facts.`);
const errorMessage = computed(() => facts.error.value instanceof Error ? facts.error.value.message : 'Check your connection and try again.');

function applyFilter(): void {
    page.value = 1;
}

function syncUrl(): void {
    if (typeof window === 'undefined') return;
    const query = new URLSearchParams();
    if (search.value.trim() !== '') query.set('search', search.value.trim());
    if (filingId.value.trim() !== '') query.set('filing_id', filingId.value.trim());
    if (scope.value !== '') query.set('scope', scope.value);
    if (normalizationStatus.value !== '') query.set('normalization_status', normalizationStatus.value);
    if (validationStatus.value !== '') query.set('validation_status', validationStatus.value);
    if (page.value > 1) query.set('page', String(page.value));
    const serialized = query.toString();
    window.history.replaceState(window.history.state, '', `${window.location.pathname}${serialized === '' ? '' : `?${serialized}`}`);
}

watch([() => search.value, filingId, scope, normalizationStatus, validationStatus, page], syncUrl);

function clearFilters(): void {
    search.input.value = '';
    search.flush();
    filingId.value = '';
    scope.value = '';
    normalizationStatus.value = '';
    validationStatus.value = '';
    page.value = 1;
    perPage.value = 25;
}

function selectFact(id: string): void {
    selectedFactId.value = id;
}

function closeDetail(): void {
    selectedFactId.value = null;
}

function statusTone(status: unknown): StatusBadgeTone {
    if (status === 'NORMALIZED' || status === 'VERIFIED' || status === 'PASS') return 'success';
    if (status === 'REVIEW_REQUIRED' || status === 'PENDING') return 'warning';
    if (status === 'FAILED' || status === 'FAIL') return 'danger';
    if (status === 'UNMAPPED' || status === 'SKIPPED') return 'neutral';
    if (status === 'PENDING') return 'warning';
    return 'neutral';
}

function factContext(row: FinancialFactListItem): string {
    return [humanize(row.scope ?? 'UNKNOWN'), humanize(row.dataType)].join(' · ');
}
</script>

<template>
    <OpsLayout current-path="/ops/financial-review" page-id="financial-review" title="Financial Review" description="Inspect normalized financial facts and their source lineage.">
        <template #status><StatusBadge :label="facts.isFetching.value ? 'Refreshing' : 'Read-only'" :tone="facts.isFetching.value ? 'info' : 'neutral'" /></template>

        <FilterBar label="Financial fact filters" content-class="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(20rem,1.8fr)_minmax(14rem,1.2fr)_repeat(3,minmax(10rem,1fr))]">
            <SearchInput id="fact-search" v-model="search.input.value" label="Search facts" placeholder="Fact ID, issuer, or source concept" input-class="w-full" @change="applyFilter" />
            <label for="fact-filing-id" class="grid min-w-0 gap-1.5 text-[13px] font-semibold leading-[18px] text-hissa-secondary">
                <span>Filing ID</span>
                <input id="fact-filing-id" v-model.trim="filingId" type="text" placeholder="FIL-..." class="min-h-10 w-full min-w-0 rounded-md border border-hissa-border bg-hissa-surface px-3 text-sm font-normal text-hissa-primary outline-none placeholder:text-hissa-muted transition-colors hover:border-hissa-border-strong focus-visible:border-hissa-action focus-visible:ring-2 focus-visible:ring-hissa-action/20" @change="applyFilter" />
            </label>
            <label for="fact-scope" class="grid min-w-0 gap-1.5 text-[13px] font-semibold leading-[18px] text-hissa-secondary">
                <span>Scope</span>
                <select id="fact-scope" v-model="scope" class="min-h-10 w-full min-w-0 rounded-md border border-hissa-border bg-hissa-surface px-3 text-sm font-normal text-hissa-primary outline-none transition-colors hover:border-hissa-border-strong focus-visible:border-hissa-action focus-visible:ring-2 focus-visible:ring-hissa-action/20" @change="applyFilter">
                    <option value="">All scopes</option>
                    <option value="CONSOLIDATED">Consolidated</option>
                    <option value="PARENT">Parent</option>
                    <option value="UNKNOWN">Unknown</option>
                </select>
            </label>
            <label for="fact-normalization-status" class="grid min-w-0 gap-1.5 text-[13px] font-semibold leading-[18px] text-hissa-secondary">
                <span>Normalization</span>
                <select id="fact-normalization-status" v-model="normalizationStatus" class="min-h-10 w-full min-w-0 rounded-md border border-hissa-border bg-hissa-surface px-3 text-sm font-normal text-hissa-primary outline-none transition-colors hover:border-hissa-border-strong focus-visible:border-hissa-action focus-visible:ring-2 focus-visible:ring-hissa-action/20" @change="applyFilter">
                    <option value="">All statuses</option>
                    <option value="NORMALIZED">Normalized</option>
                    <option value="UNMAPPED">Unmapped</option>
                    <option value="REVIEW_REQUIRED">Review required</option>
                    <option value="FAILED">Failed</option>
                </select>
            </label>
            <label for="fact-validation-status" class="grid min-w-0 gap-1.5 text-[13px] font-semibold leading-[18px] text-hissa-secondary">
                <span>Validation</span>
                <select id="fact-validation-status" v-model="validationStatus" class="min-h-10 w-full min-w-0 rounded-md border border-hissa-border bg-hissa-surface px-3 text-sm font-normal text-hissa-primary outline-none transition-colors hover:border-hissa-border-strong focus-visible:border-hissa-action focus-visible:ring-2 focus-visible:ring-hissa-action/20" @change="applyFilter">
                    <option value="">All statuses</option>
                    <option value="PENDING">Pending</option>
                    <option value="VERIFIED">Verified</option>
                    <option value="REVIEW_REQUIRED">Review required</option>
                    <option value="FAILED">Failed</option>
                </select>
            </label>
            <template #footer>
                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-hissa-border pt-3">
                    <p class="ops-meta text-hissa-secondary">{{ activeFilterCount }} {{ activeFilterCount === 1 ? 'filter' : 'filters' }} active <span aria-hidden="true">·</span> Values retain source precision.</p>
                    <button v-if="hasActiveFilters" type="button" aria-label="Clear financial fact filters" class="min-h-10 rounded-md border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-action outline-none transition-colors hover:bg-hissa-secondary-soft focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="clearFilters">Clear filters</button>
                </div>
            </template>
        </FilterBar>

        <AsyncState v-if="facts.isPending.value" state="loading" title="Loading financial facts" message="Fetching the latest normalized dataset." />
        <AsyncState v-else-if="facts.isError.value" state="error" title="Financial facts could not be loaded" :message="errorMessage">
            <template #action><button type="button" class="min-h-10 rounded-md bg-hissa-action px-3 py-2 text-sm font-semibold text-white outline-none transition-colors hover:bg-hissa-action-hover focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="facts.refetch()">Try again</button></template>
        </AsyncState>
        <AsyncState v-else-if="rows.length === 0" state="empty" title="No financial facts found" message="Try changing the filing or review filters.">
            <template #action><button v-if="hasActiveFilters" type="button" class="min-h-10 rounded-md border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-action outline-none transition-colors hover:bg-hissa-secondary-soft focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="clearFilters">Clear filters</button></template>
        </AsyncState>
        <section v-else class="overflow-hidden rounded-lg border border-hissa-border bg-hissa-surface" aria-labelledby="financial-facts-heading">
            <Toolbar>
                <template #title><h2 id="financial-facts-heading" class="ops-section-title">Normalized facts</h2></template>
                <template #description><p class="mt-1 text-sm text-hissa-secondary">{{ resultSummary }}</p></template>
                <template #actions><span v-if="facts.isFetching.value" class="ops-meta text-hissa-secondary" aria-live="polite">Refreshing…</span></template>
            </Toolbar>
            <DataTable min-width-class="min-w-[1180px]">
                <template #caption>Financial fact results</template>
                <thead class="sticky top-0 z-10">
                    <tr>
                        <th scope="col" class="w-56 whitespace-nowrap px-3 py-2.5">Fact / provenance</th>
                        <th scope="col" class="w-56 whitespace-nowrap px-3 py-2.5">Canonical concept</th>
                        <th scope="col" class="w-44 whitespace-nowrap px-3 py-2.5 text-right">Value / unit</th>
                        <th scope="col" class="w-44 whitespace-nowrap px-3 py-2.5">Period / context</th>
                        <th scope="col" class="w-48 whitespace-nowrap px-3 py-2.5">Review status</th>
                        <th scope="col" class="w-24 whitespace-nowrap px-3 py-2.5 text-right"><span class="sr-only">Action</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hissa-border">
                    <tr v-for="row in rows" :key="row.normalizedFactId" class="group bg-hissa-surface align-top hover:bg-hissa-surface-subtle">
                        <td class="px-3 py-3">
                            <p class="ops-technical max-w-[15rem] truncate font-semibold text-hissa-primary" :title="displayValue(row.normalizedFactId, 'Unknown fact')">{{ displayValue(row.normalizedFactId, 'Unknown fact') }}</p>
                            <p class="ops-wrap mt-1 max-w-[15rem] font-semibold text-hissa-primary">{{ displayValue(row.filing?.issuerCode, 'Unknown issuer') }}</p>
                            <p class="ops-wrap mt-0.5 max-w-[15rem] text-xs text-hissa-secondary" :title="displayValue(row.sourceConcept)">Source: {{ displayValue(row.sourceConcept) }}</p>
                        </td>
                        <td class="px-3 py-3">
                            <p class="ops-wrap max-w-[14rem] font-medium text-hissa-primary">{{ displayValue(row.canonicalConcept?.name, 'Unmapped') }}</p>
                            <p class="ops-technical mt-1 max-w-[14rem] truncate text-hissa-secondary" :title="displayValue(row.canonicalConcept?.code, 'No canonical concept')">{{ displayValue(row.canonicalConcept?.code, 'No canonical concept') }}</p>
                        </td>
                        <td class="px-3 py-3 text-right">
                            <p class="ops-numeric ops-wrap max-w-[14rem] text-[15px] font-semibold text-hissa-primary">{{ displayValue(row.value, 'Not recorded') }}</p>
                            <p class="ops-meta ops-wrap mt-0.5 text-hissa-secondary">{{ displayValue(row.currency, 'Unit not recorded') }}</p>
                        </td>
                        <td class="px-3 py-3">
                            <p class="ops-wrap max-w-[10rem] font-medium text-hissa-primary">{{ displayValue(row.period, 'Unknown period') }}</p>
                            <p class="mt-1 ops-meta text-hissa-secondary">{{ factContext(row) }}</p>
                        </td>
                        <td class="px-3 py-3">
                            <div class="flex flex-wrap gap-1.5">
                                <StatusBadge :label="humanize(row.normalizationStatus)" :tone="statusTone(row.normalizationStatus)" />
                                <StatusBadge :label="humanize(row.validationStatus)" :tone="statusTone(row.validationStatus)" />
                            </div>
                        </td>
                        <td class="px-3 py-3 text-right"><button type="button" class="min-h-10 whitespace-nowrap rounded-md border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-action outline-none transition-colors hover:bg-hissa-secondary-soft focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" :aria-label="`Inspect financial fact ${displayValue(row.normalizedFactId, 'unknown fact')}`" @click="selectFact(row.normalizedFactId)">Inspect</button></td>
                    </tr>
                </tbody>
            </DataTable>
            <Pagination v-if="pagination !== undefined" aria-label="Financial facts pagination" total-label="facts" :current-page="pagination.currentPage ?? 1" :last-page="pagination.lastPage ?? 1" :per-page="pagination.perPage ?? perPage" :total="pagination.total ?? rows.length" @change-page="page = $event" />
        </section>

        <DetailDialog v-if="selectedFactId !== null" :normalized-fact-id="selectedFactId" :fact="detail.data.value" :loading="detail.isPending.value" :error="detail.error.value instanceof Error ? detail.error.value.message : null" @close="closeDetail" @retry="detail.refetch()" />
    </OpsLayout>
</template>
