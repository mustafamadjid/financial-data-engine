<script setup lang="ts">
import { computed } from 'vue';

import AsyncState from '../../../components/ops/AsyncState.vue';
import DataTable from '../../../components/ops/DataTable.vue';
import OpsDialog from '../../../components/ops/OpsDialog.vue';
import StatusBadge, { type StatusBadgeTone } from '../../../components/ops/StatusBadge.vue';
import { displayValue, humanize } from '../../ops/utils/formatters';
import type { MappingImpactPreview } from '../types/conceptMapping';

const props = defineProps<{
    data: MappingImpactPreview | undefined;
    loading: boolean;
    error: string | null;
}>();

const filings = computed(() => props.data?.filings ?? []);

const emit = defineEmits<{ close: []; retry: [] }>();

function statusTone(value: unknown): StatusBadgeTone {
    if (value === 'APPROVED') return 'success';
    if (value === 'REVIEW_REQUIRED' || value === 'DRAFT') return 'warning';
    if (value === 'REJECTED') return 'danger';
    return 'neutral';
}
</script>

<template>
    <OpsDialog title-id="mapping-impact-title" description-id="mapping-impact-description" panel-class="max-w-4xl" @close="emit('close')">
        <header class="flex items-start justify-between gap-4 border-b border-hissa-border pb-4">
            <div class="min-w-0">
                <h2 id="mapping-impact-title" class="ops-section-title">Mapping impact preview</h2>
                <p id="mapping-impact-description" class="mt-1 text-sm text-hissa-secondary">Server-computed scope for the selected mapping version. Previewing does not dispatch reprocessing.</p>
            </div>
            <button type="button" aria-label="Close mapping impact preview" class="min-h-10 shrink-0 rounded-md border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-secondary outline-none transition-colors hover:bg-hissa-surface-subtle focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="emit('close')">Close</button>
        </header>

        <AsyncState v-if="loading" embedded state="loading" title="Calculating mapping impact" message="Checking affected filing revisions and preservation guarantees." />
        <AsyncState v-else-if="error" embedded state="error" title="Mapping impact could not be loaded" :message="error">
            <template #action><button type="button" class="min-h-10 rounded-md bg-hissa-action px-3 py-2 text-sm font-semibold text-white outline-none transition-colors hover:bg-hissa-action-hover focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="emit('retry')">Try again</button></template>
        </AsyncState>
        <template v-else-if="data">
            <section aria-labelledby="impact-scope-title" class="mt-5 border-b border-hissa-border pb-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 id="impact-scope-title" class="ops-section-title">Selected mapping scope</h3>
                        <p class="ops-wrap mt-1 text-sm text-hissa-secondary">{{ displayValue(data.selectedMapping?.sourceConcept, 'Unknown source concept') }} maps to {{ displayValue(data.selectedMapping?.canonicalConcept?.name, 'Unknown canonical target') }}.</p>
                    </div>
                    <StatusBadge :label="humanize(data.selectedMapping.status)" :tone="statusTone(data.selectedMapping.status)" />
                </div>
                <div class="mt-4 grid grid-cols-2 divide-x divide-y divide-hissa-border sm:grid-cols-4 sm:divide-y-0">
                    <div class="px-3 py-2.5 sm:px-4"><p class="ops-meta text-hissa-secondary">Affected filings</p><p class="ops-numeric mt-1 text-[22px] font-semibold leading-7 text-hissa-danger">{{ data.affectedFilingCount ?? 0 }}</p></div>
                    <div class="px-3 py-2.5 sm:px-4"><p class="ops-meta text-hissa-secondary">Mapping set</p><p class="ops-numeric mt-1 text-[22px] font-semibold leading-7 text-hissa-primary">v{{ displayValue(data.mappingSetVersion, 'Unknown') }}</p></div>
                    <div class="px-3 py-2.5 sm:px-4"><p class="ops-meta text-hissa-secondary">Start stage</p><p class="ops-wrap mt-1 font-semibold leading-7 text-hissa-primary">{{ displayValue(data.stageChain?.[0], 'NORMALIZE') }}</p></div>
                    <div class="px-3 py-2.5 sm:px-4"><p class="ops-meta text-hissa-secondary">Reprocess</p><p class="mt-1 font-semibold leading-7 text-hissa-warning">Separate confirmation</p></div>
                </div>
            </section>

            <section aria-labelledby="impact-filings-title" class="mt-5">
                <div class="flex flex-wrap items-baseline justify-between gap-3">
                    <div>
                        <h3 id="impact-filings-title" class="ops-section-title">Affected filing revisions</h3>
                        <p class="mt-1 text-sm text-hissa-secondary">The preview returns the revisions eligible for this mapping-set version.</p>
                    </div>
                    <span class="ops-meta text-hissa-muted">{{ filings.length }} shown of {{ data.meta?.total ?? filings.length }}</span>
                </div>
                <div class="mt-3">
                    <DataTable min-width-class="min-w-[680px]">
                        <template #caption>Affected filing revisions</template>
                        <thead class="sticky top-0 z-10">
                            <tr><th scope="col" class="w-48 whitespace-nowrap px-3 py-2.5">Filing</th><th scope="col" class="w-56 whitespace-nowrap px-3 py-2.5">Issuer / period</th><th scope="col" class="w-24 whitespace-nowrap px-3 py-2.5">Revision</th><th scope="col" class="w-32 whitespace-nowrap px-3 py-2.5">Current stage</th></tr>
                        </thead>
                        <tbody class="divide-y divide-hissa-border">
                            <tr v-for="filing in filings" :key="filing.filingId" class="align-top hover:bg-hissa-surface-subtle">
                                <td class="ops-technical ops-wrap px-3 py-3 text-hissa-primary">{{ displayValue(filing.filingId) }}</td>
                                <td class="ops-wrap px-3 py-3">{{ displayValue(filing.issuerCode, 'Unknown issuer') }} <span aria-hidden="true">·</span> {{ displayValue(filing.fiscalPeriod, 'Unknown period') }}</td>
                                <td class="ops-numeric px-3 py-3">v{{ displayValue(filing.revisionNumber, 'Unknown') }}</td>
                                <td class="ops-wrap px-3 py-3 text-hissa-secondary">{{ displayValue(filing.processingStage, 'Unknown stage') }}</td>
                            </tr>
                        </tbody>
                    </DataTable>
                </div>
            </section>

            <div class="mt-5 border-l border-hissa-info bg-hissa-info-soft px-3 py-2.5 text-sm text-hissa-info" role="status">
                Raw facts and parsed artifacts are reused; prior normalized facts remain preserved. No reprocess is dispatched by this preview.
            </div>
        </template>
    </OpsDialog>
</template>
