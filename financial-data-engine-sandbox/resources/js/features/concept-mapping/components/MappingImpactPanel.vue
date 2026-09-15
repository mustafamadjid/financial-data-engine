<script setup lang="ts">
import OpsDialog from '../../../components/ops/OpsDialog.vue';
import type { MappingImpactPreview } from '../types/conceptMapping';

defineProps<{
    data: MappingImpactPreview | undefined;
    loading: boolean;
    error: string | null;
}>();

const emit = defineEmits<{ close: []; retry: [] }>();
</script>

<template>
    <OpsDialog title-id="mapping-impact-title" panel-class="max-w-3xl" @close="emit('close')">
        <div class="flex items-start justify-between gap-4"><div><h2 id="mapping-impact-title" class="text-xl font-semibold">Impact preview</h2><p class="mt-1 text-sm text-hissa-secondary">Server-computed affected filings for the selected mapping version.</p></div><button type="button" class="rounded-lg border border-hissa-border px-3 py-2 text-sm" @click="emit('close')">Close</button></div>
        <div v-if="loading" class="mt-6 rounded-lg bg-hissa-subtle p-5 text-sm text-hissa-secondary" aria-live="polite">Calculating impact…</div>
        <div v-else-if="error" class="mt-6 rounded-lg border border-red-200 bg-red-50 p-5 text-sm text-red-700"><p>{{ error }}</p><button type="button" class="mt-3 rounded-lg bg-hissa-action px-3 py-2 font-semibold text-white" @click="emit('retry')">Try again</button></div>
        <div v-else-if="data" class="mt-6 space-y-5"><div class="grid gap-3 sm:grid-cols-3"><div class="rounded-lg bg-hissa-subtle p-4"><p class="text-xs uppercase tracking-wide text-hissa-secondary">Affected filings</p><p class="mt-1 text-2xl font-semibold">{{ data.affectedFilingCount }}</p></div><div class="rounded-lg bg-hissa-subtle p-4"><p class="text-xs uppercase tracking-wide text-hissa-secondary">Start stage</p><p class="mt-1 font-semibold">{{ data.stageChain[0] ?? 'NORMALIZE' }}</p></div><div class="rounded-lg bg-hissa-subtle p-4"><p class="text-xs uppercase tracking-wide text-hissa-secondary">Reprocess</p><p class="mt-1 font-semibold">Separate confirmation</p></div></div><div class="rounded-lg border border-hissa-border"><table class="min-w-full text-left text-sm"><caption class="sr-only">Affected filing revisions</caption><thead class="bg-hissa-subtle text-xs uppercase tracking-wide text-hissa-secondary"><tr><th class="px-4 py-3">Filing</th><th class="px-4 py-3">Issuer / period</th><th class="px-4 py-3">Revision</th><th class="px-4 py-3">Stage</th></tr></thead><tbody><tr v-for="filing in data.filings" :key="filing.filingId" class="border-t border-hissa-border"><td class="px-4 py-3 font-mono text-xs">{{ filing.filingId }}</td><td class="px-4 py-3">{{ filing.issuerCode }} · {{ filing.fiscalPeriod ?? 'Unknown period' }}</td><td class="px-4 py-3">v{{ filing.revisionNumber }}</td><td class="px-4 py-3">{{ filing.processingStage ?? 'Unknown' }}</td></tr></tbody></table></div><p class="text-sm text-hissa-secondary">Raw facts and parsed artifacts are reused; prior normalized facts remain preserved. No reprocess is dispatched by this preview.</p></div>
    </OpsDialog>
</template>
