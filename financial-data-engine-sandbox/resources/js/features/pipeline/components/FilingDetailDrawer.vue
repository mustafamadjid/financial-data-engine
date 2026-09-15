<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, toRef, watch } from 'vue';

import AsyncState from '../../../components/ops/AsyncState.vue';
import StatusBadge, { type StatusBadgeTone } from '../../../components/ops/StatusBadge.vue';
import { displayValue, formatDateTime, humanize } from '../../ops/utils/formatters';
import { usePipelineDetailQuery } from '../composables/usePipelineDetailQuery';
import PipelineHistoryPanel from './PipelineHistoryPanel.vue';
import ReprocessDialog from './ReprocessDialog.vue';

const props = withDefaults(defineProps<{
    filingId: string;
    initialHistoryOpen?: boolean;
    initialReprocessStage?: string;
    reprocessAllowed?: boolean;
    reprocessReason?: string;
}>(), { initialHistoryOpen: false, reprocessAllowed: false });
const emit = defineEmits<{ close: []; accepted: [operation: 'reprocess', filingId: string] }>();

const closeButton = ref<HTMLButtonElement | null>(null);
const dialogPanel = ref<HTMLElement | null>(null);
const historySection = ref<HTMLElement | null>(null);
const isHistoryOpen = ref(props.initialHistoryOpen);
const detail = usePipelineDetailQuery(toRef(props, 'filingId'));
const dependencyVersionSummary = computed(() => {
    const versions = detail.data.value?.currentRun?.dependencyVersions;
    if (versions === null || typeof versions !== 'object') return 'Not recorded';
    return Object.entries(versions).map(([key, value]) => `${key} ${value}`).join(', ') || 'Not recorded';
});
const stageAttempts = computed(() => detail.data.value?.stageAttempts ?? []);
const artifacts = computed(() => detail.data.value?.artifacts ?? []);
const isReprocessOpen = ref(false);
const operationNotice = ref<string | null>(null);
let opsShell: HTMLElement | null = null;

const processingTone = computed<StatusBadgeTone>(() => {
    const stage = detail.data.value?.processingStage;
    const tones: Record<string, StatusBadgeTone> = {
        DISCOVERED: 'neutral', DOWNLOADING: 'info', DOWNLOADED: 'info', PARSING: 'info', PARSED: 'info',
        NORMALIZING: 'info', NORMALIZED: 'info', VALIDATING: 'warning', VALIDATED: 'warning',
        PUBLISHING: 'warning', PUBLISHED: 'success', FAILED: 'danger',
    };
    return typeof stage === 'string' ? tones[stage] ?? 'neutral' : 'neutral';
});

const qualityTone = computed<StatusBadgeTone>(() => {
    const status = detail.data.value?.qualityStatus;
    if (status === 'PENDING') return 'info';
    if (status === 'VERIFIED') return 'success';
    if (status === 'REVIEW_REQUIRED') return 'warning';
    if (status === 'FAILED') return 'danger';
    return 'neutral';
});

function statusTone(status: unknown): StatusBadgeTone {
    if (status === 'FAILED') return 'danger';
    if (status === 'SUCCEEDED') return 'success';
    if (status === 'QUEUED' || status === 'RUNNING') return 'info';
    return 'neutral';
}

async function focusHistory(): Promise<void> {
    await nextTick();
    const historyHeading = historySection.value?.querySelector<HTMLElement>('#history-title');
    historySection.value?.scrollIntoView?.({ block: 'start' });
    historyHeading?.focus();
}

function handleReprocessAccepted(): void {
    isReprocessOpen.value = false;
    operationNotice.value = `Reprocess queued for ${props.filingId}.`;
    emit('accepted', 'reprocess', props.filingId);
}

onMounted(async () => {
    opsShell = document.querySelector<HTMLElement>('[data-ops-shell]');
    opsShell?.setAttribute('aria-hidden', 'true');
    opsShell?.setAttribute('inert', '');
    await nextTick();
    closeButton.value?.focus();
    if (isHistoryOpen.value) void focusHistory();
});

onBeforeUnmount(() => {
    opsShell?.removeAttribute('aria-hidden');
    opsShell?.removeAttribute('inert');
});

watch(isHistoryOpen, (open) => {
    if (open) void focusHistory();
});

watch(() => detail.data.value, () => {
    if (isHistoryOpen.value) void focusHistory();
}, { flush: 'post' });

function close(): void {
    emit('close');
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        event.preventDefault();
        close();
        return;
    }
    if (event.key !== 'Tab' || dialogPanel.value === null) return;
    const focusable = Array.from(dialogPanel.value.querySelectorAll<HTMLElement>('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'));
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last?.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first?.focus();
    }
}
</script>

<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-40 bg-black/30" aria-hidden="true" @click="close" />
        <aside
            ref="dialogPanel"
            role="dialog"
            :aria-modal="isReprocessOpen ? undefined : 'true'"
            :aria-hidden="isReprocessOpen ? 'true' : undefined"
            aria-labelledby="filing-detail-title"
            :inert="isReprocessOpen ? true : undefined"
            class="fixed inset-y-0 right-0 z-50 flex w-full max-w-2xl flex-col border-l border-hissa-border bg-hissa-surface shadow-lg"
            @keydown="onKeydown"
        >
        <header class="flex shrink-0 items-start justify-between gap-4 border-b border-hissa-border px-5 py-4 sm:px-6">
            <div class="min-w-0">
                <h2 id="filing-detail-title" class="ops-section-title">Filing detail</h2>
                <p class="ops-technical ops-wrap mt-1 break-all text-hissa-secondary">{{ displayValue(filingId) }}</p>
                <div v-if="detail.data.value !== undefined" class="mt-2 flex flex-wrap gap-2">
                    <StatusBadge :label="humanize(detail.data.value.processingStage)" :tone="processingTone" />
                    <StatusBadge :label="humanize(detail.data.value.qualityStatus)" :tone="qualityTone" />
                </div>
            </div>
            <button ref="closeButton" type="button" aria-label="Close filing detail" class="min-h-10 shrink-0 rounded-md border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-secondary outline-none transition-colors hover:bg-hissa-surface-subtle focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="close">Close</button>
        </header>

        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5 sm:px-6">
            <AsyncState v-if="detail.isPending.value" embedded state="loading" title="Loading filing detail" message="Fetching run status, attempts, and source artifacts." />
            <AsyncState v-else-if="detail.isError.value" embedded state="error" title="Filing detail could not be loaded" :message="detail.error.value instanceof Error ? detail.error.value.message : 'Check your connection and try again.'">
                <template #action><button type="button" class="min-h-10 rounded-md bg-hissa-action px-3 py-2 text-sm font-semibold text-white outline-none transition-colors hover:bg-hissa-action-hover focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="detail.refetch()">Try again</button></template>
            </AsyncState>
            <template v-else-if="detail.data.value !== undefined">
                <section aria-label="Filing metadata" class="border-b border-hissa-border pb-5">
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-4 text-sm">
                        <div>
                            <dt class="ops-meta text-hissa-secondary">Issuer</dt>
                            <dd class="ops-wrap mt-0.5 font-semibold text-hissa-primary">{{ displayValue(detail.data.value.issuerCode, 'Unknown issuer') }}</dd>
                        </div>
                        <div>
                            <dt class="ops-meta text-hissa-secondary">Report type</dt>
                            <dd class="ops-wrap mt-0.5 font-semibold text-hissa-primary">{{ displayValue(detail.data.value.reportType, 'Unknown report type') }}</dd>
                        </div>
                        <div>
                            <dt class="ops-meta text-hissa-secondary">Fiscal period</dt>
                            <dd class="ops-wrap mt-0.5 font-semibold text-hissa-primary">{{ displayValue(detail.data.value.fiscalPeriod, 'Unknown period') }} <span aria-hidden="true">·</span> {{ displayValue(detail.data.value.fiscalYear, 'Unknown year') }}</dd>
                        </div>
                        <div>
                            <dt class="ops-meta text-hissa-secondary">Revision</dt>
                            <dd class="ops-numeric mt-0.5 font-semibold text-hissa-primary">{{ displayValue(detail.data.value.revisionNumber, 'Unknown') }}</dd>
                        </div>
                    </dl>
                    <div v-if="detail.data.value.latestError != null" role="alert" class="mt-4 border-l border-hissa-danger bg-hissa-danger-soft/40 px-3 py-2">
                        <p class="ops-meta font-semibold uppercase tracking-[0.04em] text-hissa-danger">Latest error</p>
                        <p class="ops-wrap mt-1 text-sm text-hissa-danger">{{ displayValue(detail.data.value.latestError?.message, 'No error detail recorded') }}</p>
                    </div>
                </section>

                <section class="border-b border-hissa-border py-5" aria-labelledby="current-run-title">
                    <h3 id="current-run-title" class="ops-section-title">Current pipeline run</h3>
                    <div v-if="detail.data.value.currentRun != null" class="mt-3 divide-y divide-hissa-border border-y border-hissa-border text-sm">
                        <div class="flex flex-wrap justify-between gap-3 py-2.5"><span class="text-hissa-secondary">Status</span><span class="ops-wrap text-right font-semibold text-hissa-primary">{{ humanize(detail.data.value.currentRun.status) }}</span></div>
                        <div class="flex flex-wrap justify-between gap-3 py-2.5"><span class="text-hissa-secondary">Started</span><time class="text-right text-hissa-primary">{{ formatDateTime(detail.data.value.currentRun.startedAt) }}</time></div>
                        <div class="flex flex-wrap justify-between gap-3 py-2.5"><span class="text-hissa-secondary">Dependencies</span><span class="max-w-[70%] text-right ops-technical text-hissa-primary">{{ dependencyVersionSummary }}</span></div>
                        <div class="flex flex-wrap justify-between gap-3 py-2.5"><span class="text-hissa-secondary">Correlation ID</span><span class="ops-wrap max-w-[70%] break-all text-right ops-technical text-hissa-primary">{{ displayValue(detail.data.value.currentRun.correlationId) }}</span></div>
                    </div>
                    <p v-else class="mt-3 text-sm text-hissa-secondary">No pipeline run has been recorded for this filing.</p>
                </section>

                <section class="border-b border-hissa-border py-5" aria-labelledby="attempts-title">
                    <div class="flex items-baseline justify-between gap-3">
                        <h3 id="attempts-title" class="ops-section-title">Stage attempts</h3>
                        <span class="ops-meta text-hissa-muted">{{ stageAttempts.length }} recorded</span>
                    </div>
                    <ol v-if="stageAttempts.length > 0" class="mt-3 divide-y divide-hissa-border border-y border-hissa-border">
                        <li v-for="attempt in stageAttempts" :key="attempt.jobRunId" class="py-3 text-sm">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="ops-wrap font-semibold text-hissa-primary">{{ displayValue(attempt.stage, 'Unknown stage') }} <span class="font-normal text-hissa-muted">/ Attempt {{ displayValue(attempt.attempt, 'Unknown') }}</span></p>
                                <StatusBadge :label="humanize(attempt.status)" :tone="statusTone(attempt.status)" />
                            </div>
                            <p class="mt-1 ops-meta text-hissa-secondary">{{ formatDateTime(attempt.startedAt) }}<span aria-hidden="true"> - </span>{{ formatDateTime(attempt.finishedAt) }}</p>
                            <p class="ops-wrap mt-1 break-all ops-technical text-hissa-secondary">{{ displayValue(attempt.correlationId) }}</p>
                            <p v-if="attempt.error != null" class="ops-wrap mt-2 border-l border-hissa-danger pl-2 text-hissa-danger">{{ displayValue(attempt.error?.message, 'No error detail recorded') }}</p>
                        </li>
                    </ol>
                    <p v-else class="mt-3 text-sm text-hissa-secondary">No stage attempts have been recorded.</p>
                </section>

                <section class="border-b border-hissa-border py-5" aria-labelledby="artifacts-title">
                    <div class="flex items-baseline justify-between gap-3">
                        <h3 id="artifacts-title" class="ops-section-title">Source artifacts</h3>
                        <span class="ops-meta text-hissa-muted">{{ artifacts.length }} available</span>
                    </div>
                    <ul v-if="artifacts.length > 0" class="mt-3 divide-y divide-hissa-border border-y border-hissa-border">
                        <li v-for="artifact in artifacts" :key="artifact.artifactId" class="flex items-center justify-between gap-3 py-3 text-sm">
                            <div class="min-w-0">
                                <p class="ops-wrap font-medium text-hissa-primary" :title="displayValue(artifact.filename)">{{ displayValue(artifact.filename, 'Unnamed artifact') }}</p>
                                <p class="ops-wrap ops-meta text-hissa-secondary">{{ displayValue(artifact.artifactType, 'Unknown artifact') }} <span aria-hidden="true">·</span> {{ displayValue(artifact.contentType, 'Unknown content type') }}</p>
                            </div>
                            <a :href="`/ops/data/pipeline-filings/${encodeURIComponent(filingId)}/artifacts/${encodeURIComponent(displayValue(artifact.artifactId, 'unknown'))}`" class="inline-flex min-h-10 shrink-0 items-center rounded-md px-2.5 py-2 text-sm font-semibold text-hissa-action outline-none transition-colors hover:bg-hissa-secondary-soft focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2">Download</a>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-hissa-secondary">No valid source artifact is available.</p>
                </section>

                <div v-if="isHistoryOpen" ref="historySection" class="mt-5">
                    <PipelineHistoryPanel :filing-id="filingId" />
                </div>
            </template>
        </div>

        <footer v-if="detail.data.value !== undefined" class="flex shrink-0 flex-col items-stretch gap-3 border-t border-hissa-border bg-hissa-surface px-5 py-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:px-6">
            <p v-if="operationNotice !== null" role="status" aria-live="polite" class="basis-full border-l border-hissa-success bg-hissa-success-soft px-3 py-2 text-sm text-hissa-success">{{ operationNotice }}</p>
            <button type="button" class="min-h-10 rounded-md border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-action outline-none transition-colors hover:bg-hissa-secondary-soft focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="isHistoryOpen = !isHistoryOpen">{{ isHistoryOpen ? 'Hide history' : 'View processing history' }}</button>
            <button v-if="props.reprocessAllowed" type="button" class="min-h-10 rounded-md border border-hissa-danger/40 px-3 py-2 text-sm font-semibold text-hissa-danger outline-none transition-colors hover:bg-hissa-danger-soft focus-visible:ring-2 focus-visible:ring-hissa-danger focus-visible:ring-offset-2" @click="isReprocessOpen = true">Reprocess</button>
            <p v-else class="ops-wrap max-w-full text-left text-xs text-hissa-secondary sm:max-w-[22rem] sm:text-right">{{ props.reprocessReason ?? 'Reprocess is not available for this filing.' }}</p>
        </footer>
        </aside>
        <ReprocessDialog v-if="isReprocessOpen" :filing-id="filingId" :initial-stage="props.initialReprocessStage" @close="isReprocessOpen = false" @accepted="handleReprocessAccepted" />
    </Teleport>
</template>
