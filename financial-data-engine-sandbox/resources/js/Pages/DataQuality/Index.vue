<script setup lang="ts">
import { computed, defineAsyncComponent, ref, watch } from 'vue';

import AsyncState from '../../components/ops/AsyncState.vue';
import DataTable from '../../components/ops/DataTable.vue';
import FilterBar from '../../components/ops/FilterBar.vue';
import Pagination from '../../components/ops/Pagination.vue';
import StatusBadge, { type StatusBadgeTone } from '../../components/ops/StatusBadge.vue';
import Toolbar from '../../components/ops/Toolbar.vue';
import { displayValue, formatDateTime, humanize } from '../../features/ops/utils/formatters';
import OpsLayout from '../../layouts/OpsLayout.vue';
import ValidationSummary from '../../features/data-quality/components/ValidationSummary.vue';
import { useValidationDetailQuery, useValidationResultsQuery, useValidationSummaryQuery } from '../../features/data-quality/queries/useDataQualityQuery';
import type { ValidationListParams, ValidationResult, ValidationResultItem, ValidationSeverity } from '../../features/data-quality/types/dataQuality';

defineOptions({ name: 'DataQualityIndex' });

const DetailDialog = defineAsyncComponent(() => import('../../features/data-quality/components/ValidationDetailDialog.vue'));
const initial = typeof window === 'undefined' ? new URLSearchParams() : new URLSearchParams(window.location.search);
const filingId = ref(initial.get('filing_id') ?? '');
const datasetVersion = ref(initial.get('dataset_version') ?? '');
const ruleSetVersion = ref(initial.get('rule_set_version') ?? '');
const severity = ref<ValidationSeverity | ''>((initial.get('severity') as ValidationSeverity | null) ?? '');
const result = ref<ValidationResult | ''>((initial.get('result') as ValidationResult | null) ?? '');
const page = ref(Number(initial.get('page') ?? 1));
const selectedId = ref<string | null>(null);
const params = computed<ValidationListParams>(() => ({ page: page.value, perPage: 25, severity: severity.value || undefined, result: result.value || undefined }));
const list = useValidationResultsQuery(filingId, datasetVersion, ruleSetVersion, params);
const summary = useValidationSummaryQuery(filingId, datasetVersion, ruleSetVersion);
const detail = useValidationDetailQuery(selectedId, filingId, datasetVersion, ruleSetVersion);
const rows = computed(() => list.data.value?.data ?? []);
const pagination = computed(() => list.data.value?.meta);
const hasExecution = computed(() => filingId.value !== '' && datasetVersion.value !== '' && ruleSetVersion.value !== '');
const activeIssueFilterCount = computed(() => [severity.value, result.value].filter((value) => value !== '').length);
const hasIssueFilters = computed(() => activeIssueFilterCount.value > 0);
const resultSummary = computed(() => pagination.value === undefined ? 'Review rule outcomes, affected facts, and validation context.' : `Showing ${rows.value.length} of ${pagination.value.total ?? 0} validation results.`);
const listError = computed(() => list.error.value instanceof Error ? list.error.value : null);
const summaryError = computed(() => summary.error.value instanceof Error ? summary.error.value.message : null);
const errorMessage = computed(() => listError.value?.message ?? 'Check your connection and try again.');

function applyFilter(): void {
    page.value = 1;
}

function syncUrl(): void {
    if (typeof window === 'undefined') return;
    const query = new URLSearchParams();
    if (filingId.value.trim() !== '') query.set('filing_id', filingId.value.trim());
    if (datasetVersion.value.trim() !== '') query.set('dataset_version', datasetVersion.value.trim());
    if (ruleSetVersion.value.trim() !== '') query.set('rule_set_version', ruleSetVersion.value.trim());
    if (severity.value !== '') query.set('severity', severity.value);
    if (result.value !== '') query.set('result', result.value);
    if (page.value > 1) query.set('page', String(page.value));
    const serialized = query.toString();
    window.history.replaceState(window.history.state, '', `${window.location.pathname}${serialized === '' ? '' : `?${serialized}`}`);
}

watch([filingId, datasetVersion, ruleSetVersion, severity, result, page], syncUrl);

function clearIssueFilters(): void {
    severity.value = '';
    result.value = '';
    page.value = 1;
}

function resultLabel(value: ValidationResult): string {
    return value === 'REVIEW_REQUIRED' ? 'Review required' : humanize(value);
}

function severityLabel(value: ValidationSeverity): string {
    return value === 'WARN' ? 'Warning' : humanize(value);
}

function resultTone(value: ValidationResult): StatusBadgeTone {
    if (value === 'PASS') return 'success';
    if (value === 'FAIL') return 'danger';
    if (value === 'REVIEW_REQUIRED') return 'warning';
    return 'neutral';
}

function severityTone(value: ValidationSeverity): StatusBadgeTone {
    if (value === 'ERROR') return 'danger';
    if (value === 'WARN') return 'warning';
    if (value === 'INFO') return 'info';
    return 'neutral';
}

function qualityStatusLabel(value: string): string {
    return humanize(value);
}

function qualityStatusTone(value: string): StatusBadgeTone {
    if (value === 'FAILED') return 'danger';
    if (value === 'REVIEW_REQUIRED') return 'warning';
    if (value === 'VERIFIED') return 'success';
    if (value === 'PENDING') return 'info';
    return 'neutral';
}

function comparisonSummary(row: ValidationResultItem): string | null {
    const parts = [
        row.expectedValue == null ? null : `Expected ${row.expectedValue}`,
        row.actualValue == null ? null : `Actual ${row.actualValue}`,
        row.tolerance == null ? null : `Tolerance ${row.tolerance}`,
    ].filter((value): value is string => value !== null);

    return parts.length > 0 ? parts.join(' · ') : null;
}
</script>

<template>
    <OpsLayout current-path="/ops/data-quality" page-id="data-quality" title="Data Quality" description="Inspect version-scoped validation outcomes and their input facts.">
        <template #status>
            <StatusBadge v-if="!hasExecution" label="Execution required" tone="warning" />
            <StatusBadge v-else :label="list.isFetching.value || summary.isFetching.value ? 'Refreshing' : 'Read-only'" :tone="list.isFetching.value || summary.isFetching.value ? 'info' : 'neutral'" />
        </template>

        <FilterBar label="Validation execution" content-class="grid min-w-0 grid-cols-1 gap-3 md:grid-cols-3">
            <label for="quality-filing-id" class="grid min-w-0 gap-1.5 text-[13px] font-semibold leading-[18px] text-hissa-secondary">
                <span>Filing ID</span>
                <input id="quality-filing-id" v-model.trim="filingId" type="text" placeholder="FIL-..." class="min-h-10 w-full min-w-0 rounded-md border border-hissa-border bg-hissa-surface px-3 text-sm font-normal text-hissa-primary outline-none placeholder:text-hissa-muted transition-colors hover:border-hissa-border-strong focus-visible:border-hissa-action focus-visible:ring-2 focus-visible:ring-hissa-action/20" @change="applyFilter" />
            </label>
            <label for="quality-dataset-version" class="grid min-w-0 gap-1.5 text-[13px] font-semibold leading-[18px] text-hissa-secondary">
                <span>Dataset version</span>
                <input id="quality-dataset-version" v-model.trim="datasetVersion" type="text" placeholder="dataset-v2" class="min-h-10 w-full min-w-0 rounded-md border border-hissa-border bg-hissa-surface px-3 text-sm font-normal text-hissa-primary outline-none placeholder:text-hissa-muted transition-colors hover:border-hissa-border-strong focus-visible:border-hissa-action focus-visible:ring-2 focus-visible:ring-hissa-action/20" @change="applyFilter" />
            </label>
            <label for="quality-rule-set-version" class="grid min-w-0 gap-1.5 text-[13px] font-semibold leading-[18px] text-hissa-secondary">
                <span>Rule-set version</span>
                <input id="quality-rule-set-version" v-model.trim="ruleSetVersion" type="text" placeholder="rules-v3" class="min-h-10 w-full min-w-0 rounded-md border border-hissa-border bg-hissa-surface px-3 text-sm font-normal text-hissa-primary outline-none placeholder:text-hissa-muted transition-colors hover:border-hissa-border-strong focus-visible:border-hissa-action focus-visible:ring-2 focus-visible:ring-hissa-action/20" @change="applyFilter" />
            </label>
            <template #footer>
                <div class="flex flex-wrap items-start justify-between gap-2 border-t border-hissa-border pt-3">
                    <p id="quality-execution-help" class="max-w-3xl text-sm text-hissa-secondary">Select all three execution identifiers before querying. Results from another dataset or rule-set are never mixed.</p>
                    <span class="ops-meta whitespace-nowrap text-hissa-muted">Scope: one filing execution</span>
                </div>
            </template>
        </FilterBar>

        <section v-if="!hasExecution" aria-labelledby="quality-execution-required" class="flex flex-wrap items-start justify-between gap-4 rounded-lg border border-hissa-border bg-hissa-surface px-5 py-4">
            <div class="min-w-0">
                <h2 id="quality-execution-required" class="ops-section-title">Select an execution to inspect</h2>
                <p class="mt-1 max-w-2xl text-sm text-hissa-secondary">Enter a filing, dataset version, and rule-set version to see validation outcomes, severity, and linked input facts.</p>
            </div>
            <StatusBadge label="Context required" tone="warning" />
        </section>

        <template v-else>
            <ValidationSummary :summary="summary.data.value" :is-loading="summary.isPending.value" :error="summaryError" />

            <FilterBar label="Validation issue filters" content-class="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2">
                <label for="quality-severity" class="grid min-w-0 gap-1.5 text-[13px] font-semibold leading-[18px] text-hissa-secondary">
                    <span>Severity</span>
                    <select id="quality-severity" v-model="severity" class="min-h-10 w-full min-w-0 rounded-md border border-hissa-border bg-hissa-surface px-3 text-sm font-normal text-hissa-primary outline-none transition-colors hover:border-hissa-border-strong focus-visible:border-hissa-action focus-visible:ring-2 focus-visible:ring-hissa-action/20" @change="applyFilter">
                        <option value="">All severities</option>
                        <option value="ERROR">Error</option>
                        <option value="WARN">Warning</option>
                        <option value="INFO">Info</option>
                    </select>
                </label>
                <label for="quality-result" class="grid min-w-0 gap-1.5 text-[13px] font-semibold leading-[18px] text-hissa-secondary">
                    <span>Result</span>
                    <select id="quality-result" v-model="result" class="min-h-10 w-full min-w-0 rounded-md border border-hissa-border bg-hissa-surface px-3 text-sm font-normal text-hissa-primary outline-none transition-colors hover:border-hissa-border-strong focus-visible:border-hissa-action focus-visible:ring-2 focus-visible:ring-hissa-action/20" @change="applyFilter">
                        <option value="">All results</option>
                        <option value="PASS">Pass</option>
                        <option value="FAIL">Fail</option>
                        <option value="REVIEW_REQUIRED">Review required</option>
                        <option value="SKIPPED">Skipped</option>
                    </select>
                </label>
                <template #footer>
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-hissa-border pt-3">
                        <p class="ops-meta text-hissa-secondary">{{ activeIssueFilterCount }} {{ activeIssueFilterCount === 1 ? 'issue filter' : 'issue filters' }} active</p>
                        <button v-if="hasIssueFilters" type="button" aria-label="Clear validation issue filters" class="min-h-10 rounded-md border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-action outline-none transition-colors hover:bg-hissa-secondary-soft focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="clearIssueFilters">Clear filters</button>
                    </div>
                </template>
            </FilterBar>

            <section aria-labelledby="quality-table-heading" class="overflow-hidden rounded-lg border border-hissa-border bg-hissa-surface">
                <Toolbar>
                    <template #title><h2 id="quality-table-heading" class="ops-section-title">Validation results</h2></template>
                    <template #description><p class="ops-wrap mt-1 text-sm text-hissa-secondary">{{ resultSummary }} <span aria-hidden="true">·</span> <span class="ops-technical ops-wrap">{{ displayValue(filingId, 'Unknown filing') }}</span></p></template>
                    <template #actions>
                        <a :href="`/ops/pipeline?filing_id=${encodeURIComponent(filingId)}`" class="inline-flex min-h-10 items-center rounded-md border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-action outline-none transition-colors hover:bg-hissa-secondary-soft focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2">Open filing in Pipeline</a>
                        <StatusBadge v-if="summary.data.value" :label="qualityStatusLabel(summary.data.value.qualityStatus)" :tone="qualityStatusTone(summary.data.value.qualityStatus)" />
                    </template>
                </Toolbar>

                <AsyncState v-if="list.isPending.value" embedded state="loading" title="Loading validation results" message="Fetching the selected quality execution." />
                <AsyncState v-else-if="list.isError.value" embedded state="error" title="Validation results could not be loaded" :message="errorMessage">
                    <template #action><button type="button" class="min-h-10 rounded-md bg-hissa-action px-3 py-2 text-sm font-semibold text-white outline-none transition-colors hover:bg-hissa-action-hover focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="list.refetch()">Try again</button></template>
                </AsyncState>
                <AsyncState v-else-if="rows.length === 0" embedded state="empty" title="No validation results found" message="Try another execution or clear the issue filters.">
                    <template #action><button v-if="hasIssueFilters" type="button" class="min-h-10 rounded-md border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-action outline-none transition-colors hover:bg-hissa-secondary-soft focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="clearIssueFilters">Clear filters</button></template>
                </AsyncState>
                <template v-else>
                    <DataTable min-width-class="min-w-[900px] xl:min-w-[1160px]">
                        <template #caption>Data quality validation results</template>
                        <thead class="sticky top-0 z-10">
                            <tr>
                                <th scope="col" class="w-56 whitespace-nowrap px-3 py-2.5">Validation rule</th>
                                <th scope="col" class="w-28 whitespace-nowrap px-3 py-2.5">Severity</th>
                                <th scope="col" class="w-32 whitespace-nowrap px-3 py-2.5">Result</th>
                                <th scope="col" class="w-[22rem] px-3 py-2.5">Issue context</th>
                                <th scope="col" class="w-48 px-3 py-2.5">Input facts</th>
                                <th scope="col" class="hidden w-40 whitespace-nowrap px-3 py-2.5 xl:table-cell">Checked</th>
                                <th scope="col" class="w-24 whitespace-nowrap px-3 py-2.5 text-right"><span class="sr-only">Action</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-hissa-border">
                            <tr v-for="row in rows" :key="row.validationResultId" :data-row-state="String(row.severity ?? 'unknown').toLowerCase()" class="group bg-hissa-surface align-top hover:bg-hissa-surface-subtle">
                                <td class="px-3 py-3">
                                    <p class="ops-technical max-w-[16rem] truncate font-semibold text-hissa-primary" :title="displayValue(row.rule?.code, 'Unknown rule')">{{ displayValue(row.rule?.code, 'Unknown rule') }}</p>
                                    <p class="mt-1 text-sm text-hissa-primary">v{{ displayValue(row.rule?.version, 'Unknown') }}</p>
                                    <p class="ops-wrap mt-1 max-w-[16rem] text-xs text-hissa-secondary" :title="displayValue(row.rule?.description, 'No rule description')">{{ displayValue(row.rule?.description, 'No rule description') }}</p>
                                </td>
                                <td class="px-3 py-3"><StatusBadge :label="severityLabel(row.severity)" :tone="severityTone(row.severity)" /></td>
                                <td class="px-3 py-3"><StatusBadge :label="resultLabel(row.result)" :tone="resultTone(row.result)" /></td>
                                <td class="px-3 py-3">
                                    <p class="ops-wrap max-w-[26rem] text-sm text-hissa-primary">{{ displayValue(row.message, 'No validation message was provided.') }}</p>
                                    <p v-if="comparisonSummary(row) !== null" class="ops-wrap mt-1 max-w-[26rem] text-xs leading-[18px] text-hissa-secondary">{{ comparisonSummary(row) }}</p>
                                </td>
                                <td class="px-3 py-3">
                                    <p class="text-sm font-medium text-hissa-primary">{{ row.inputFacts?.length ?? 0 }} linked fact{{ (row.inputFacts?.length ?? 0) === 1 ? '' : 's' }}</p>
                                    <ul v-if="(row.inputFacts?.length ?? 0) > 0" class="mt-1 space-y-1">
                                        <li v-for="fact in (row.inputFacts ?? []).slice(0, 2)" :key="fact.normalizedFactId">
                                            <a v-if="fact.href" :href="fact.href" class="ops-technical block max-w-[11rem] truncate text-hissa-action underline underline-offset-2 outline-none hover:text-hissa-action-hover focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" :title="displayValue(fact.normalizedFactId, 'Unknown fact')">{{ displayValue(fact.normalizedFactId, 'Unknown fact') }}</a>
                                            <span v-else class="ops-technical block max-w-[11rem] truncate text-hissa-secondary" :title="displayValue(fact.normalizedFactId, 'Unknown fact')">{{ displayValue(fact.normalizedFactId, 'Unknown fact') }}</span>
                                        </li>
                                    </ul>
                                    <p v-if="(row.inputFacts?.length ?? 0) > 2" class="mt-1 ops-meta text-hissa-muted">+{{ (row.inputFacts?.length ?? 0) - 2 }} more linked</p>
                                </td>
                                <td class="hidden px-3 py-3 xl:table-cell"><time class="ops-meta whitespace-nowrap text-hissa-secondary" :datetime="row.checkedAt ?? undefined" :title="formatDateTime(row.checkedAt)">{{ formatDateTime(row.checkedAt) }}</time></td>
                                <td class="px-3 py-3 text-right"><button type="button" class="min-h-10 whitespace-nowrap rounded-md border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-action outline-none transition-colors hover:bg-hissa-secondary-soft focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" :aria-label="`Inspect validation result ${displayValue(row.rule?.code, 'unknown rule')}`" @click="selectedId = row.validationResultId">Inspect</button></td>
                            </tr>
                        </tbody>
                    </DataTable>
                    <Pagination v-if="pagination !== undefined" aria-label="Validation results pagination" total-label="results" :current-page="pagination.currentPage ?? 1" :last-page="pagination.lastPage ?? 1" :per-page="pagination.perPage ?? 25" :total="pagination.total ?? rows.length" @change-page="page = $event" />
                </template>
            </section>
        </template>

        <DetailDialog v-if="selectedId !== null" :validation-result-id="selectedId" :detail="detail.data.value" :loading="detail.isPending.value" :error="detail.error.value instanceof Error ? detail.error.value.message : null" @close="selectedId = null" @retry="detail.refetch()" />
    </OpsLayout>
</template>
