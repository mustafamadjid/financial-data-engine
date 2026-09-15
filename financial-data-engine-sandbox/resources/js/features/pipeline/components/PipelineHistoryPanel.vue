<script setup lang="ts">
import { computed, ref, toRef } from 'vue';

import AsyncState from '../../../components/ops/AsyncState.vue';
import Pagination from '../../../components/ops/Pagination.vue';
import { displayValue, formatDateTime, humanize } from '../../ops/utils/formatters';
import { usePipelineHistoryQuery } from '../composables/usePipelineDetailQuery';
import type { PipelineHistoryParams } from '../types/pipeline';

const props = defineProps<{ filingId: string }>();
const params = ref<PipelineHistoryParams>({ page: 1, perPage: 10 });
const history = usePipelineHistoryQuery(toRef(props, 'filingId'), params);
const error = computed(() => history.error.value instanceof Error ? history.error.value : null);
const entries = computed(() => history.data.value?.data ?? []);
const total = computed(() => history.data.value?.meta?.total ?? entries.value.length);

function goToPage(page: number): void {
    if (page >= 1 && page <= (history.data.value?.meta?.lastPage ?? 1)) {
        params.value = { ...params.value, page };
    }
}
</script>

<template>
    <section aria-labelledby="history-title" class="border-t border-hissa-border pt-5">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <h3 id="history-title" tabindex="-1" class="ops-section-title outline-none">Processing &amp; audit history</h3>
                <p class="mt-1 text-sm text-hissa-secondary">Read-only operational events.</p>
            </div>
            <span class="ops-technical ops-wrap max-w-full text-right text-hissa-muted">{{ displayValue(filingId) }}</span>
        </div>

        <AsyncState v-if="history.isPending.value" embedded state="loading" title="Loading processing history" message="Fetching read-only operational events." />
        <AsyncState v-else-if="history.isError.value" embedded state="error" title="History could not be loaded" :message="error?.message ?? 'Check your connection and try again.'">
            <template #action><button type="button" class="min-h-10 rounded-md bg-hissa-action px-3 py-2 text-sm font-semibold text-white outline-none transition-colors hover:bg-hissa-action-hover focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="history.refetch()">Try again</button></template>
        </AsyncState>
        <template v-else-if="history.data.value !== undefined">
            <ol class="mt-4 divide-y divide-hissa-border border-y border-hissa-border">
                <li v-for="entry in entries" :key="entry.id" :data-history-entry="entry.id" class="relative py-3 pl-5 text-sm">
                    <span aria-hidden="true" class="absolute left-0 top-5 size-2 rounded-full bg-hissa-action ring-4 ring-hissa-surface" />
                    <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-1">
                        <p class="ops-wrap font-semibold text-hissa-primary">{{ humanize(entry.type) }} <span class="font-normal text-hissa-muted">/</span> {{ humanize(entry.action ?? entry.status ?? 'Recorded event') }}</p>
                        <time class="ops-meta whitespace-nowrap text-right text-hissa-secondary" :datetime="entry.occurredAt ?? undefined">{{ formatDateTime(entry.occurredAt, 'Time not recorded') }}</time>
                    </div>
                    <dl class="mt-1 flex flex-wrap gap-x-3 gap-y-1 ops-meta text-hissa-secondary">
                        <div v-if="entry.stage != null"><dt class="inline font-semibold">Stage</dt> <dd class="inline ops-technical ops-wrap">{{ displayValue(entry.stage) }}</dd></div>
                        <div v-if="entry.attempt != null"><dt class="inline font-semibold">Attempt</dt> <dd class="inline ops-numeric">{{ displayValue(entry.attempt) }}</dd></div>
                        <div v-if="entry.actorId != null"><dt class="inline font-semibold">Actor</dt> <dd class="inline ops-technical ops-wrap">{{ displayValue(entry.actorId) }}</dd></div>
                        <div v-if="entry.correlationId != null" class="max-w-full"><dt class="inline font-semibold">Correlation</dt> <dd class="inline ops-technical ops-wrap break-all">{{ displayValue(entry.correlationId) }}</dd></div>
                    </dl>
                    <p v-if="entry.rationale != null" class="ops-wrap mt-2 text-hissa-secondary">{{ displayValue(entry.rationale) }}</p>
                    <p v-if="entry.error != null" class="ops-wrap mt-2 border-l-2 border-hissa-danger pl-2 text-hissa-danger">{{ displayValue(entry.error?.message, 'No error detail recorded') }}</p>
                </li>
            </ol>
            <p v-if="entries.length === 0" class="border-y border-hissa-border px-3 py-6 text-center text-sm text-hissa-secondary">No history events have been recorded.</p>
            <Pagination
                aria-label="History pagination"
                total-label="events"
                :current-page="history.data.value.meta?.currentPage ?? 1"
                :last-page="history.data.value.meta?.lastPage ?? 1"
                :per-page="history.data.value.meta?.perPage ?? params.perPage"
                :total="total"
                @change-page="goToPage"
            />
        </template>
    </section>
</template>
