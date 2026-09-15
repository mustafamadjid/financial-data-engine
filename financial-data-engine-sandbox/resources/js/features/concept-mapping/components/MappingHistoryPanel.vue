<script setup lang="ts">
import { computed } from 'vue';

import AsyncState from '../../../components/ops/AsyncState.vue';
import DataTable from '../../../components/ops/DataTable.vue';
import OpsDialog from '../../../components/ops/OpsDialog.vue';
import StatusBadge, { type StatusBadgeTone } from '../../../components/ops/StatusBadge.vue';
import { displayValue, formatDateTime, humanize } from '../../ops/utils/formatters';
import type { MappingHistoryResponse } from '../types/conceptMapping';

const props = defineProps<{
    seriesKey: string;
    data: MappingHistoryResponse | undefined;
    loading: boolean;
    error: string | null;
}>();

const items = computed(() => props.data?.data ?? []);
const total = computed(() => props.data?.meta?.total ?? items.value.length);

const emit = defineEmits<{ close: []; retry: [] }>();

function statusTone(value: unknown): StatusBadgeTone {
    if (value === 'APPROVED') return 'success';
    if (value === 'REVIEW_REQUIRED' || value === 'DRAFT') return 'warning';
    if (value === 'REJECTED') return 'danger';
    return 'neutral';
}
</script>

<template>
    <OpsDialog title-id="mapping-history-title" description-id="mapping-history-description" panel-class="max-w-4xl" @close="emit('close')">
        <header class="flex items-start justify-between gap-4 border-b border-hissa-border pb-4">
            <div class="min-w-0">
                <h2 id="mapping-history-title" class="ops-section-title">Mapping version history</h2>
                <p id="mapping-history-description" class="mt-1 text-sm text-hissa-secondary">Append-only versions for this source-to-target mapping series.</p>
                <p class="ops-technical ops-wrap mt-2 text-hissa-secondary" :title="displayValue(seriesKey)">{{ displayValue(seriesKey) }}</p>
            </div>
            <button type="button" aria-label="Close mapping version history" class="min-h-10 shrink-0 rounded-md border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-secondary outline-none transition-colors hover:bg-hissa-surface-subtle focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="emit('close')">Close</button>
        </header>

        <AsyncState v-if="loading" embedded state="loading" title="Loading mapping history" message="Fetching the append-only version record." />
        <AsyncState v-else-if="error" embedded state="error" title="Mapping history could not be loaded" :message="error">
            <template #action><button type="button" class="min-h-10 rounded-md bg-hissa-action px-3 py-2 text-sm font-semibold text-white outline-none transition-colors hover:bg-hissa-action-hover focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="emit('retry')">Try again</button></template>
        </AsyncState>
        <AsyncState v-else-if="items.length === 0" embedded state="empty" title="No mapping versions recorded" message="This series has no persisted version history yet." />
        <template v-else-if="data">
            <div class="mt-5 flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm text-hissa-secondary">{{ total }} recorded version{{ total === 1 ? '' : 's' }} <span aria-hidden="true">·</span> newest version first</p>
                <StatusBadge :label="`Current v${data.data?.[0]?.version ?? '—'}`" tone="info" />
            </div>
            <div class="mt-3">
                <DataTable min-width-class="min-w-[860px]">
                    <template #caption>Mapping version history for {{ seriesKey }}</template>
                    <thead class="sticky top-0 z-10">
                        <tr>
                            <th scope="col" class="w-28 whitespace-nowrap px-3 py-2.5">Version</th>
                            <th scope="col" class="w-60 whitespace-nowrap px-3 py-2.5">Canonical target</th>
                            <th scope="col" class="w-36 whitespace-nowrap px-3 py-2.5">Status</th>
                            <th scope="col" class="w-[22rem] px-3 py-2.5">Rationale / evidence</th>
                            <th scope="col" class="w-44 whitespace-nowrap px-3 py-2.5">Recorded</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hissa-border">
                        <tr v-for="item in items" :key="item.mappingRuleId" class="align-top hover:bg-hissa-surface-subtle">
                            <td class="px-3 py-3">
                                <p class="ops-numeric font-semibold text-hissa-primary">v{{ displayValue(item.version, 'Unknown') }}</p>
                                <p class="ops-technical ops-wrap mt-1 max-w-[8rem] truncate text-hissa-secondary" :title="displayValue(item.mappingRuleId)">{{ displayValue(item.mappingRuleId) }}</p>
                                <p v-if="item.supersedesMappingRuleId" class="mt-1 ops-meta text-hissa-muted">Supersedes prior rule</p>
                            </td>
                            <td class="px-3 py-3">
                                <p class="ops-wrap font-medium text-hissa-primary">{{ displayValue(item.canonicalConcept?.name, 'Canonical concept unavailable') }}</p>
                                <p class="ops-technical ops-wrap mt-1 text-hissa-secondary">{{ displayValue(item.canonicalConcept?.code) }}</p>
                            </td>
                            <td class="px-3 py-3"><StatusBadge :label="humanize(item.status)" :tone="statusTone(item.status)" /></td>
                            <td class="px-3 py-3 text-hissa-secondary">
                                <p class="ops-wrap">{{ displayValue(item.rationale, 'No rationale recorded.') }}</p>
                                <p class="ops-wrap mt-2 ops-meta"><span class="font-semibold">Evidence:</span> {{ (item.evidenceIds ?? []).length > 0 ? (item.evidenceIds ?? []).join(', ') : 'None linked' }}</p>
                            </td>
                            <td class="px-3 py-3">
                                <time class="ops-meta whitespace-nowrap text-hissa-secondary" :datetime="item.createdAt ?? undefined">{{ formatDateTime(item.createdAt) }}</time>
                                <p class="ops-wrap mt-1 ops-meta text-hissa-muted">{{ displayValue(item.createdBy ?? item.reviewer, 'Actor not recorded') }}</p>
                            </td>
                        </tr>
                    </tbody>
                </DataTable>
            </div>
        </template>
    </OpsDialog>
</template>
