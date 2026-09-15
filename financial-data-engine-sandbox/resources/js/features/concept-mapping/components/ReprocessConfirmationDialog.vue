<script setup lang="ts">
import { computed, ref } from 'vue';

import DataTable from '../../../components/ops/DataTable.vue';
import OpsDialog from '../../../components/ops/OpsDialog.vue';
import { displayValue } from '../../ops/utils/formatters';
import type { MappingImpactPreview } from '../types/conceptMapping';

const props = defineProps<{
    data: MappingImpactPreview;
    submitting: boolean;
    error: string | null;
}>();

const emit = defineEmits<{
    close: [];
    submit: [reason: string];
}>();

const reason = ref('');
const filings = computed(() => props.data?.filings ?? []);
const stageChain = computed(() => props.data?.stageChain ?? []);

function submit(): void {
    if (props.submitting || filings.value.length === 0 || reason.value.trim().length < 3) return;
    emit('submit', reason.value.trim());
}

function preserved(value: unknown): string {
    return value === true ? 'Preserved' : 'Not guaranteed';
}
</script>

<template>
    <OpsDialog title-id="mapping-reprocess-title" description-id="mapping-reprocess-description" panel-class="max-w-3xl" @close="emit('close')">
        <header class="flex items-start justify-between gap-4 border-b border-hissa-border pb-4">
            <div class="min-w-0">
                <h2 id="mapping-reprocess-title" class="ops-section-title">Reprocess affected filings</h2>
                <p id="mapping-reprocess-description" class="ops-wrap mt-1 text-sm text-hissa-secondary">Queue the selected revisions with mapping set version {{ displayValue(data.mappingSetVersion, 'unknown') }}.</p>
            </div>
            <button type="button" aria-label="Close reprocess confirmation" class="min-h-10 shrink-0 rounded-md border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-secondary outline-none transition-colors hover:bg-hissa-surface-subtle focus-visible:ring-2 focus-visible:ring-hissa-danger focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60" :disabled="submitting" @click="emit('close')">Close</button>
        </header>

        <div class="mt-5 border-l border-hissa-danger bg-hissa-danger-soft px-3 py-2.5 text-sm text-hissa-danger" role="alert">
            This is a high-impact recovery action. It queues {{ filings.length }} selected filing revision{{ filings.length === 1 ? '' : 's' }} for normalization and may change downstream normalized values.
        </div>

        <dl class="mt-5 grid grid-cols-2 divide-x divide-y divide-hissa-border border-y border-hissa-border sm:grid-cols-4 sm:divide-y-0">
            <div class="px-3 py-3 sm:px-4"><dt class="ops-meta text-hissa-secondary">Selected</dt><dd class="ops-numeric mt-1 text-[22px] font-semibold leading-7 text-hissa-danger">{{ filings.length }}</dd><p class="ops-meta mt-0.5 text-hissa-muted">of {{ data.affectedFilingCount ?? filings.length }} affected</p></div>
            <div class="px-3 py-3 sm:px-4"><dt class="ops-meta text-hissa-secondary">Stage chain</dt><dd class="ops-wrap mt-1 font-semibold leading-7 text-hissa-primary">{{ stageChain.length > 0 ? stageChain.join(' → ') : 'Unknown stage chain' }}</dd></div>
            <div class="px-3 py-3 sm:px-4"><dt class="ops-meta text-hissa-secondary">Raw / parsed</dt><dd class="mt-1 font-semibold leading-7 text-hissa-primary">{{ preserved(data.upstream?.rawFactsReused) }} / {{ preserved(data.upstream?.parsedArtifactsReused) }}</dd></div>
            <div class="px-3 py-3 sm:px-4"><dt class="ops-meta text-hissa-secondary">Prior normalized</dt><dd class="mt-1 font-semibold leading-7 text-hissa-primary">{{ preserved(data.upstream?.priorNormalizedFactsPreserved) }}</dd></div>
        </dl>

        <div class="mt-5 max-h-48 overflow-y-auto">
            <DataTable min-width-class="min-w-[620px]">
                <template #caption>Selected filing revisions for reprocess</template>
                <thead class="sticky top-0 z-10"><tr><th scope="col" class="w-48 whitespace-nowrap px-3 py-2.5">Filing</th><th scope="col" class="w-56 whitespace-nowrap px-3 py-2.5">Issuer / period</th><th scope="col" class="w-24 whitespace-nowrap px-3 py-2.5">Revision</th></tr></thead>
                <tbody class="divide-y divide-hissa-border">
                    <tr v-for="filing in filings" :key="filing.filingId" class="align-top hover:bg-hissa-surface-subtle"><td class="ops-technical ops-wrap px-3 py-3">{{ displayValue(filing.filingId) }}</td><td class="ops-wrap px-3 py-3">{{ displayValue(filing.issuerCode, 'Unknown issuer') }} <span aria-hidden="true">·</span> {{ displayValue(filing.fiscalPeriod, 'Unknown period') }}</td><td class="ops-numeric px-3 py-3">v{{ displayValue(filing.revisionNumber, 'Unknown') }}</td></tr>
                </tbody>
            </DataTable>
        </div>

        <form class="mt-5 space-y-4" @submit.prevent="submit">
            <label for="mapping-reprocess-reason" class="grid gap-1.5 text-[13px] font-semibold leading-[18px] text-hissa-primary">
                <span>Reason <span class="font-normal text-hissa-secondary">(required)</span></span>
                <textarea id="mapping-reprocess-reason" v-model="reason" required minlength="3" rows="4" class="min-h-24 w-full resize-y rounded-md border border-hissa-border bg-hissa-surface px-3 py-2 text-sm font-normal text-hissa-primary outline-none placeholder:text-hissa-muted transition-colors hover:border-hissa-border-strong focus-visible:border-hissa-danger focus-visible:ring-2 focus-visible:ring-hissa-danger/20" placeholder="Explain why these filing revisions need to be reprocessed." />
            </label>
            <p class="ops-meta text-hissa-secondary">Accepted requests are queued asynchronously. Saving a mapping version never dispatches reprocessing automatically.</p>
            <p v-if="error" class="border-l border-hissa-danger bg-hissa-danger-soft px-3 py-2.5 text-sm text-hissa-danger" role="alert">{{ error }}</p>
            <div class="flex flex-wrap justify-end gap-2 border-t border-hissa-border pt-4">
                <button type="button" class="min-h-10 rounded-md border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-secondary outline-none transition-colors hover:bg-hissa-surface-subtle focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60" :disabled="submitting" @click="emit('close')">Cancel</button>
                <button type="submit" class="min-h-10 rounded-md bg-hissa-danger px-3 py-2 text-sm font-semibold text-white outline-none transition-colors hover:opacity-90 focus-visible:ring-2 focus-visible:ring-hissa-danger focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60" :disabled="submitting || reason.trim().length < 3" :aria-busy="submitting">{{ submitting ? 'Queueing…' : 'Confirm reprocess' }}</button>
            </div>
        </form>
    </OpsDialog>
</template>
