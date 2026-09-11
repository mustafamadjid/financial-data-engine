<script setup lang="ts">
import { computed, defineAsyncComponent, nextTick, onMounted, ref, toRef } from 'vue';

import { usePipelineDetailQuery } from '../composables/usePipelineDetailQuery';

const props = withDefaults(defineProps<{ filingId: string; initialHistoryOpen?: boolean }>(), { initialHistoryOpen: false });
const emit = defineEmits<{ close: [] }>();

const closeButton = ref<HTMLButtonElement | null>(null);
const isHistoryOpen = ref(props.initialHistoryOpen);
const detail = usePipelineDetailQuery(toRef(props, 'filingId'));
const detailError = computed(() => detail.error.value instanceof Error ? detail.error.value : null);
const dependencyVersionSummary = computed(() => {
    const versions = detail.data.value?.currentRun?.dependencyVersions;
    return versions === undefined ? 'Not recorded' : Object.entries(versions).map(([key, value]) => `${key} ${value}`).join(', ') || 'Not recorded';
});
const PipelineHistoryPanel = defineAsyncComponent(() => import('./PipelineHistoryPanel.vue'));
const ReprocessDialog = defineAsyncComponent(() => import('./ReprocessDialog.vue'));
const isReprocessOpen = ref(false);

onMounted(async () => {
    await nextTick();
    closeButton.value?.focus();
});

function close(): void {
    emit('close');
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        event.preventDefault();
        close();
    }
}
</script>

<template>
    <div class="fixed inset-0 z-40 bg-black/30" aria-hidden="true" @click="close" />
    <aside
        role="dialog"
        aria-modal="true"
        aria-labelledby="filing-detail-title"
        class="fixed inset-y-0 right-0 z-50 flex w-full max-w-xl flex-col border-l border-hissa-border bg-hissa-surface shadow-xl"
        @keydown="onKeydown"
    >
        <header class="flex items-start justify-between border-b border-hissa-border px-6 py-5">
            <div>
                <p class="text-sm font-semibold text-hissa-action">Filing detail</p>
                <h2 id="filing-detail-title" class="mt-1 text-xl font-semibold text-hissa-primary">{{ filingId }}</h2>
            </div>
            <button ref="closeButton" type="button" aria-label="Close filing detail" class="rounded-lg px-3 py-2 text-sm font-semibold text-hissa-secondary outline-none hover:bg-hissa-subtle focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="close">Close</button>
        </header>

        <div class="min-h-0 flex-1 overflow-y-auto p-6">
            <div v-if="detail.isPending.value" aria-busy="true" class="space-y-3">
                <div v-for="placeholder in 4" :key="placeholder" class="h-12 animate-pulse rounded-lg bg-hissa-subtle" />
            </div>
            <div v-else-if="detail.isError.value" role="alert" class="rounded-xl border border-hissa-danger/30 bg-red-50 p-4">
                <h3 class="font-semibold text-hissa-primary">Filing detail could not be loaded.</h3>
                <p class="mt-1 text-sm text-hissa-secondary">{{ detailError?.message ?? 'Check your connection and try again.' }}</p>
                <button type="button" class="mt-3 rounded-lg bg-hissa-action px-3 py-2 text-sm font-semibold text-white outline-none focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="detail.refetch()">Try again</button>
            </div>
            <template v-else-if="detail.data.value !== undefined">
                <section aria-label="Filing metadata" class="space-y-2 rounded-xl border border-hissa-border p-4 text-sm">
                    <p><span class="font-semibold">Issuer:</span> {{ detail.data.value.issuerCode }}</p>
                    <p><span class="font-semibold">Period:</span> {{ detail.data.value.fiscalPeriod }} {{ detail.data.value.fiscalYear }} · Revision {{ detail.data.value.revisionNumber }}</p>
                    <p><span class="font-semibold">Quality:</span> {{ detail.data.value.qualityStatus.replace('_', ' ') }}</p>
                    <p v-if="detail.data.value.latestError !== null" class="text-hissa-danger"><span class="font-semibold">Latest error:</span> {{ detail.data.value.latestError.message }}</p>
                </section>

                <section class="mt-5" aria-labelledby="current-run-title">
                    <h3 id="current-run-title" class="text-base font-semibold">Current pipeline run</h3>
                    <div v-if="detail.data.value.currentRun !== null" class="mt-2 rounded-xl border border-hissa-border p-4 text-sm">
                        <p><span class="font-semibold">Status:</span> {{ detail.data.value.currentRun.status }}</p>
                        <p><span class="font-semibold">Correlation ID:</span> {{ detail.data.value.currentRun.correlationId }}</p>
                        <p><span class="font-semibold">Dependencies:</span> {{ dependencyVersionSummary }}</p>
                    </div>
                    <p v-else class="mt-2 text-sm text-hissa-secondary">No pipeline run has been recorded for this filing.</p>
                </section>

                <section class="mt-5" aria-labelledby="attempts-title">
                    <h3 id="attempts-title" class="text-base font-semibold">Stage attempts</h3>
                    <ul class="mt-2 space-y-2">
                        <li v-for="attempt in detail.data.value.stageAttempts" :key="attempt.jobRunId" class="rounded-lg border border-hissa-border p-3 text-sm">
                            <span class="font-semibold">{{ attempt.stage }}</span> · Attempt {{ attempt.attempt }} · {{ attempt.status }}
                            <p v-if="attempt.error !== null" class="mt-1 text-hissa-danger">{{ attempt.error.message }}</p>
                        </li>
                    </ul>
                </section>

                <section class="mt-5" aria-labelledby="artifacts-title">
                    <h3 id="artifacts-title" class="text-base font-semibold">Source artifacts</h3>
                    <ul class="mt-2 space-y-2">
                        <li v-for="artifact in detail.data.value.artifacts" :key="artifact.artifactId" class="flex items-center justify-between gap-3 rounded-lg border border-hissa-border p-3 text-sm">
                            <span>{{ artifact.filename }} · {{ artifact.artifactType }}</span>
                            <a :href="`/ops/data/pipeline-filings/${encodeURIComponent(filingId)}/artifacts/${encodeURIComponent(artifact.artifactId)}`" class="font-semibold text-hissa-action outline-none focus-visible:ring-2 focus-visible:ring-hissa-action">Download</a>
                        </li>
                    </ul>
                    <p v-if="detail.data.value.artifacts.length === 0" class="mt-2 text-sm text-hissa-secondary">No valid source artifact is available.</p>
                </section>

                <button type="button" class="mt-5 rounded-lg border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-action outline-none hover:bg-hissa-secondary-soft focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="isHistoryOpen = !isHistoryOpen">{{ isHistoryOpen ? 'Hide history' : 'View processing history' }}</button>
                <button type="button" class="ml-2 mt-5 rounded-lg border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-danger outline-none hover:bg-red-50 focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="isReprocessOpen = true">Reprocess</button>
                <PipelineHistoryPanel v-if="isHistoryOpen" :filing-id="filingId" class="mt-3" />
            </template>
        </div>
    </aside>
    <ReprocessDialog v-if="isReprocessOpen" :filing-id="filingId" @close="isReprocessOpen = false" @accepted="isReprocessOpen = false" />
</template>
