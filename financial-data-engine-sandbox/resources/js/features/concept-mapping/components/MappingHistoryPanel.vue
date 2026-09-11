<script setup lang="ts">
import OpsDialog from '../../../components/ops/OpsDialog.vue';
import StatusBadge from '../../../components/ops/StatusBadge.vue';
import type { MappingHistoryResponse } from '../types/conceptMapping';

defineProps<{
    seriesKey: string;
    data: MappingHistoryResponse | undefined;
    loading: boolean;
    error: string | null;
}>();

const emit = defineEmits<{ close: []; retry: [] }>();
</script>

<template>
    <OpsDialog title-id="mapping-history-title" panel-class="max-w-3xl" @close="emit('close')">
        <div class="flex items-start justify-between gap-4">
            <div><h2 id="mapping-history-title" class="text-xl font-semibold">Version history</h2><p class="mt-1 text-sm text-hissa-secondary">Append-only history for <span class="font-mono text-xs">{{ seriesKey }}</span></p></div>
            <button type="button" class="rounded-lg border border-hissa-border px-3 py-2 text-sm" @click="emit('close')">Close</button>
        </div>
        <div v-if="loading" class="mt-6 rounded-lg bg-hissa-subtle p-5 text-sm text-hissa-secondary" aria-live="polite">Loading mapping history…</div>
        <div v-else-if="error" class="mt-6 rounded-lg border border-red-200 bg-red-50 p-5 text-sm text-red-700"><p>{{ error }}</p><button type="button" class="mt-3 rounded-lg bg-hissa-action px-3 py-2 font-semibold text-white" @click="emit('retry')">Try again</button></div>
        <div v-else-if="data?.data.length === 0" class="mt-6 rounded-lg bg-hissa-subtle p-5 text-sm text-hissa-secondary">No persisted mapping versions exist for this series.</div>
        <div v-else class="mt-6 overflow-x-auto rounded-lg border border-hissa-border">
            <table class="min-w-full text-left text-sm"><caption class="sr-only">Mapping version history</caption><thead class="bg-hissa-subtle text-xs uppercase tracking-wide text-hissa-secondary"><tr><th class="px-4 py-3">Version</th><th class="px-4 py-3">Canonical concept</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Rationale / evidence</th></tr></thead><tbody><tr v-for="item in data?.data ?? []" :key="item.mappingRuleId" class="border-t border-hissa-border align-top"><td class="px-4 py-4 font-semibold">v{{ item.version }}<p class="mt-1 font-mono text-[11px] text-hissa-secondary">{{ item.mappingRuleId }}</p></td><td class="px-4 py-4"><p class="font-medium">{{ item.canonicalConcept.name }}</p><p class="text-xs text-hissa-secondary">{{ item.canonicalConcept.code }}</p></td><td class="px-4 py-4"><StatusBadge :label="item.status" :tone="item.status === 'APPROVED' ? 'success' : item.status === 'REVIEW_REQUIRED' ? 'warning' : 'neutral'" /></td><td class="px-4 py-4 text-hissa-secondary"><p>{{ item.rationale ?? 'No rationale recorded.' }}</p><p class="mt-2 text-xs">Evidence: {{ item.evidenceIds.length ? item.evidenceIds.join(', ') : 'None linked' }}</p></td></tr></tbody></table>
        </div>
    </OpsDialog>
</template>
