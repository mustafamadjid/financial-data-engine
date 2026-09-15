<script setup lang="ts">
import { computed, defineAsyncComponent, ref } from 'vue';
import { useDebouncedValue } from '../../features/ops/composables/useDebouncedValue';
import { useFinancialFactDetailQuery, useFinancialFactsQuery } from '../../features/financial-review/queries/useFinancialReviewQuery';
import type { FinancialFactListParams } from '../../features/financial-review/types/financialReview';
import OpsLayout from '../../layouts/OpsLayout.vue';
import AsyncState from '../../components/ops/AsyncState.vue';
import DataTable from '../../components/ops/DataTable.vue';
import StatusBadge from '../../components/ops/StatusBadge.vue';

const DetailDialog = defineAsyncComponent(() => import('../../features/financial-review/components/FinancialFactDetailDialog.vue'));
const initialSearch = typeof window === 'undefined' ? '' : new URLSearchParams(window.location.search).get('search') ?? '';
const search = useDebouncedValue(initialSearch, 300);
const filingId = ref(typeof window === 'undefined' ? '' : new URLSearchParams(window.location.search).get('filing_id') ?? '');
const scope = ref('');
const normalizationStatus = ref('');
const validationStatus = ref('');
const page = ref(Number(new URLSearchParams(typeof window === 'undefined' ? '' : window.location.search).get('page') ?? 1));
const perPage = ref(25);
const selectedFactId = ref<string | null>(null);

const params = computed<FinancialFactListParams>(() => ({ filingId: filingId.value.trim() || undefined, search: search.value.trim() || undefined, scope: scope.value || undefined, normalizationStatus: normalizationStatus.value || undefined, validationStatus: validationStatus.value || undefined, page: page.value, perPage: perPage.value, sort: 'filing_asc' }));
const facts = useFinancialFactsQuery(params);
const detail = useFinancialFactDetailQuery(selectedFactId);
const rows = computed(() => facts.data.value?.data ?? []);
const pagination = computed(() => facts.data.value?.meta);
const errorMessage = computed(() => facts.error.value instanceof Error ? facts.error.value.message : 'Check your connection and try again.');

function applyFilter(): void { page.value = 1; }
function selectFact(id: string): void { selectedFactId.value = id; }
function closeDetail(): void { selectedFactId.value = null; }
function statusTone(status: string): 'neutral' | 'success' | 'warning' | 'danger' { if (status === 'NORMALIZED' || status === 'VERIFIED' || status === 'PASS') return 'success'; if (status === 'REVIEW_REQUIRED' || status === 'PENDING') return 'warning'; if (status === 'FAILED' || status === 'FAIL') return 'danger'; return 'neutral'; }
</script>

<template>
    <OpsLayout page-id="financial-review" title="Financial Review" description="Inspect normalized financial facts and their source lineage.">
        <template #status><StatusBadge :label="facts.isFetching.value ? 'Refreshing' : 'Read-only'" :tone="facts.isFetching.value ? 'info' : 'neutral'" /></template>
        <section class="rounded-xl border border-hissa-border bg-hissa-surface p-4 sm:p-5" aria-labelledby="financial-review-filters">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end">
                <div class="min-w-0 flex-1"><label id="financial-review-filters" for="fact-search" class="text-sm font-semibold">Search facts</label><input id="fact-search" v-model="search.input.value" type="search" placeholder="Fact ID, issuer, or source concept" class="mt-1 min-h-10 w-full rounded-lg border border-hissa-border bg-hissa-surface px-3 text-sm outline-none focus:border-hissa-action focus:ring-2 focus:ring-hissa-action/20" @change="applyFilter"></div>
                <label class="text-sm font-medium lg:w-44">Scope<select v-model="scope" class="mt-1 min-h-10 w-full rounded-lg border border-hissa-border bg-hissa-surface px-3 text-sm" @change="applyFilter"><option value="">All scopes</option><option value="CONSOLIDATED">Consolidated</option><option value="PARENT">Parent</option><option value="UNKNOWN">Unknown</option></select></label>
                <label class="text-sm font-medium lg:w-48">Normalization<select v-model="normalizationStatus" class="mt-1 min-h-10 w-full rounded-lg border border-hissa-border bg-hissa-surface px-3 text-sm" @change="applyFilter"><option value="">All statuses</option><option value="NORMALIZED">Normalized</option><option value="UNMAPPED">Unmapped</option><option value="REVIEW_REQUIRED">Review required</option><option value="FAILED">Failed</option></select></label>
                <label class="text-sm font-medium lg:w-44">Validation<select v-model="validationStatus" class="mt-1 min-h-10 w-full rounded-lg border border-hissa-border bg-hissa-surface px-3 text-sm" @change="applyFilter"><option value="">All statuses</option><option value="PENDING">Pending</option><option value="VERIFIED">Verified</option><option value="REVIEW_REQUIRED">Review required</option><option value="FAILED">Failed</option></select></label>
            </div>
        </section>
        <AsyncState v-if="facts.isPending.value" state="loading" title="Loading financial facts" message="Fetching the latest normalized dataset." />
        <AsyncState v-else-if="facts.isError.value" state="error" title="Financial facts could not be loaded" :message="errorMessage"><template #action><button type="button" class="rounded-lg bg-hissa-action px-3 py-2 text-sm font-semibold text-white" @click="facts.refetch()">Try again</button></template></AsyncState>
        <AsyncState v-else-if="rows.length === 0" state="empty" title="No financial facts found" message="Try changing the filing or review filters." />
        <section v-else class="overflow-hidden rounded-xl border border-hissa-border bg-hissa-surface" aria-labelledby="financial-facts-heading">
            <div class="flex items-center justify-between border-b border-hissa-border px-4 py-4 sm:px-6"><div><h2 id="financial-facts-heading" class="text-lg font-semibold">Normalized facts</h2><p class="mt-1 text-sm text-hissa-secondary">{{ pagination?.total ?? rows.length }} facts · values retain source precision.</p></div><span v-if="facts.isFetching.value" class="text-sm text-hissa-secondary" aria-live="polite">Refreshing…</span></div>
            <DataTable min-width-class="min-w-[980px]"><template #caption>Financial fact results</template><thead class="bg-hissa-subtle text-xs uppercase tracking-wide text-hissa-secondary"><tr><th class="px-4 py-3 font-semibold">Fact / issuer</th><th class="px-4 py-3 font-semibold">Canonical concept</th><th class="px-4 py-3 text-right font-semibold">Value</th><th class="px-4 py-3 font-semibold">Period</th><th class="px-4 py-3 font-semibold">Status</th><th class="px-4 py-3 text-right font-semibold">Action</th></tr></thead><tbody><tr v-for="row in rows" :key="row.normalizedFactId" class="border-t border-hissa-border align-top hover:bg-hissa-subtle/70"><td class="px-4 py-4"><p class="font-mono text-xs text-hissa-secondary">{{ row.normalizedFactId }}</p><p class="mt-1 font-semibold">{{ row.filing?.issuerCode ?? 'Unknown issuer' }}</p><p class="text-xs text-hissa-secondary">{{ row.sourceConcept }}</p></td><td class="px-4 py-4"><p class="font-medium">{{ row.canonicalConcept?.name ?? 'Unmapped' }}</p><p class="text-xs text-hissa-secondary">{{ row.canonicalConcept?.code ?? 'No canonical concept' }}</p></td><td class="px-4 py-4 text-right font-mono text-sm">{{ row.value ?? '—' }} <span v-if="row.currency" class="text-xs text-hissa-secondary">{{ row.currency }}</span></td><td class="px-4 py-4 text-sm"><p>{{ row.period ?? 'Unknown period' }}</p><p class="text-xs text-hissa-secondary">{{ row.scope ?? 'UNKNOWN' }}</p></td><td class="space-y-1 px-4 py-4"><StatusBadge :label="row.normalizationStatus" :tone="statusTone(row.normalizationStatus)" /><StatusBadge :label="row.validationStatus" :tone="statusTone(row.validationStatus)" /></td><td class="px-4 py-4 text-right"><button type="button" class="rounded-lg border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-action hover:bg-hissa-secondary-soft focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-hissa-action" @click="selectFact(row.normalizedFactId)">Inspect</button></td></tr></tbody></DataTable>
            <div v-if="pagination && pagination.lastPage > 1" class="flex items-center justify-between border-t border-hissa-border px-4 py-3 text-sm"><span class="text-hissa-secondary">Page {{ pagination.currentPage }} of {{ pagination.lastPage }}</span><div class="flex gap-2"><button type="button" class="rounded-lg border border-hissa-border px-3 py-2 disabled:opacity-50" :disabled="page <= 1" @click="page--">Previous</button><button type="button" class="rounded-lg border border-hissa-border px-3 py-2 disabled:opacity-50" :disabled="page >= pagination.lastPage" @click="page++">Next</button></div></div>
        </section>
        <DetailDialog v-if="selectedFactId !== null" :fact="detail.data.value" :loading="detail.isPending.value" :error="detail.error.value instanceof Error ? detail.error.value.message : null" @close="closeDetail" @retry="detail.refetch" />
    </OpsLayout>
</template>
