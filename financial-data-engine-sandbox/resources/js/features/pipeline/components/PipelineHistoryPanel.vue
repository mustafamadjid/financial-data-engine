<script setup lang="ts">
import { computed, ref, toRef } from 'vue';

import { usePipelineHistoryQuery } from '../composables/usePipelineDetailQuery';
import type { PipelineHistoryParams } from '../types/pipeline';

const props = defineProps<{ filingId: string }>();
const params = ref<PipelineHistoryParams>({ page: 1, perPage: 10 });
const history = usePipelineHistoryQuery(toRef(props, 'filingId'), params);
const error = computed(() => history.error.value instanceof Error ? history.error.value : null);

function goToPage(page: number): void {
    if (page >= 1 && page <= (history.data.value?.meta.lastPage ?? 1)) {
        params.value = { ...params.value, page };
    }
}
</script>

<template>
    <section aria-labelledby="history-title" class="rounded-xl border border-hissa-border p-4">
        <h3 id="history-title" class="text-base font-semibold">Processing &amp; audit history</h3>
        <p class="mt-1 text-sm text-hissa-secondary">Read-only operational events.</p>
        <div v-if="history.isPending.value" aria-busy="true" class="mt-3 space-y-2">
            <div v-for="placeholder in 3" :key="placeholder" class="h-10 animate-pulse rounded bg-hissa-subtle" />
        </div>
        <div v-else-if="history.isError.value" role="alert" class="mt-3 text-sm text-hissa-danger">
            <p>{{ error?.message ?? 'History could not be loaded.' }}</p>
            <button type="button" class="mt-2 font-semibold text-hissa-action" @click="history.refetch()">Try again</button>
        </div>
        <template v-else-if="history.data.value !== undefined">
            <ol class="mt-3 space-y-2">
                <li v-for="entry in history.data.value.data" :key="entry.id" class="rounded-lg bg-hissa-subtle p-3 text-sm">
                    <p class="font-semibold">{{ entry.type }} · {{ entry.action ?? entry.status ?? 'Recorded event' }}</p>
                    <p v-if="entry.actorId !== null" class="mt-1 text-hissa-secondary">Actor: {{ entry.actorId }}</p>
                    <p v-if="entry.rationale !== null" class="mt-1 text-hissa-secondary">{{ entry.rationale }}</p>
                    <p v-if="entry.error !== null" class="mt-1 text-hissa-danger">{{ entry.error.message }}</p>
                </li>
            </ol>
            <nav v-if="history.data.value.meta.lastPage > 1" aria-label="History pagination" class="mt-3 flex items-center justify-between text-sm">
                <button type="button" :disabled="params.page === 1" class="font-semibold text-hissa-action disabled:text-hissa-secondary" @click="goToPage(params.page - 1)">Previous</button>
                <span>Page {{ history.data.value.meta.currentPage }} of {{ history.data.value.meta.lastPage }}</span>
                <button type="button" :disabled="params.page === history.data.value.meta.lastPage" class="font-semibold text-hissa-action disabled:text-hissa-secondary" @click="goToPage(params.page + 1)">Next</button>
            </nav>
        </template>
    </section>
</template>
