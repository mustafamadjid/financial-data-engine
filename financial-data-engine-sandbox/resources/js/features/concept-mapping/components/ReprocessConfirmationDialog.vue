<script setup lang="ts">
import { ref } from 'vue';

import OpsDialog from '../../../components/ops/OpsDialog.vue';
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

function submit(): void {
    if (props.submitting || reason.value.trim().length < 3) return;
    emit('submit', reason.value.trim());
}
</script>

<template>
    <OpsDialog title-id="mapping-reprocess-title" panel-class="max-w-3xl" @close="emit('close')">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-wide text-hissa-secondary">Async operation</p>
                <h2 id="mapping-reprocess-title" class="mt-1 text-xl font-semibold">Reprocess affected filings</h2>
                <p class="mt-1 text-sm text-hissa-secondary">The selected revisions will be normalized with mapping version {{ data.mappingSetVersion }}.</p>
            </div>
            <button type="button" class="rounded-lg border border-hissa-border px-3 py-2 text-sm" :disabled="submitting" @click="emit('close')">Close</button>
        </div>
        <div class="mt-5 grid gap-3 sm:grid-cols-3">
            <div class="rounded-lg bg-hissa-subtle p-4"><p class="text-xs uppercase tracking-wide text-hissa-secondary">Selected filings</p><p class="mt-1 text-2xl font-semibold">{{ data.filings.length }} / {{ data.affectedFilingCount }}</p></div>
            <div class="rounded-lg bg-hissa-subtle p-4"><p class="text-xs uppercase tracking-wide text-hissa-secondary">Stage chain</p><p class="mt-1 font-semibold">{{ data.stageChain.join(' → ') }}</p></div>
            <div class="rounded-lg bg-hissa-subtle p-4"><p class="text-xs uppercase tracking-wide text-hissa-secondary">Preservation</p><p class="mt-1 font-semibold">Prior data preserved</p></div>
        </div>
        <div class="mt-5 max-h-48 overflow-auto rounded-lg border border-hissa-border"><table class="min-w-full text-left text-sm"><caption class="sr-only">Selected filing revisions for reprocess</caption><thead class="bg-hissa-subtle text-xs uppercase tracking-wide text-hissa-secondary"><tr><th class="px-4 py-3">Filing</th><th class="px-4 py-3">Issuer / period</th><th class="px-4 py-3">Revision</th></tr></thead><tbody><tr v-for="filing in data.filings" :key="filing.filingId" class="border-t border-hissa-border"><td class="px-4 py-3 font-mono text-xs">{{ filing.filingId }}</td><td class="px-4 py-3">{{ filing.issuerCode }} · {{ filing.fiscalPeriod ?? 'Unknown period' }}</td><td class="px-4 py-3">v{{ filing.revisionNumber }}</td></tr></tbody></table></div>
        <form class="mt-5 space-y-4" @submit.prevent="submit">
            <label class="block text-sm font-medium">Reason<textarea v-model="reason" required minlength="3" rows="3" class="mt-1 w-full rounded-lg border border-hissa-border bg-hissa-surface px-3 py-2 text-sm" placeholder="Explain why these filings need to be reprocessed."></textarea></label>
            <p class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">Accepted requests are queued asynchronously. Saving a mapping version never dispatches reprocessing automatically.</p>
            <p v-if="error" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700" role="alert">{{ error }}</p>
            <div class="flex justify-end gap-2"><button type="button" class="rounded-lg border border-hissa-border px-4 py-2 text-sm font-semibold" :disabled="submitting" @click="emit('close')">Cancel</button><button type="submit" class="rounded-lg bg-hissa-action px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50" :disabled="submitting || reason.trim().length < 3">{{ submitting ? 'Queueing…' : 'Confirm reprocess' }}</button></div>
        </form>
    </OpsDialog>
</template>
