<script setup lang="ts">
import { computed, ref } from 'vue';

import OpsDialog from '../../../components/ops/OpsDialog.vue';
import StatusBadge, { type StatusBadgeTone } from '../../../components/ops/StatusBadge.vue';
import { displayValue, humanize } from '../../ops/utils/formatters';
import type { CanonicalOption, CreateMappingVersionPayload, MappingInventoryItem, MappingStatus } from '../types/conceptMapping';

const props = defineProps<{
    selected: MappingInventoryItem;
    canonicalOptions: CanonicalOption[];
    submitting: boolean;
    error: string | null;
}>();

const emit = defineEmits<{
    close: [];
    submit: [payload: CreateMappingVersionPayload];
}>();

const canonicalConcept = ref(props.selected.currentMapping?.canonicalConcept?.code ?? '');
const status = ref<MappingStatus>(props.selected.currentMapping?.status ?? 'DRAFT');
const rationale = ref('');
const periodType = ref<'INSTANT' | 'DURATION' | null>(props.selected.currentMapping?.periodType === 'DURATION' ? 'DURATION' : props.selected.currentMapping?.periodType === 'INSTANT' ? 'INSTANT' : null);
const signConvention = ref(props.selected.currentMapping?.signConvention ?? '');
const allowedScope = ref(props.selected.currentMapping?.allowedScope?.join(', ') ?? '');
const evidenceIds = ref(props.selected.currentMapping?.evidenceIds?.join(', ') ?? '');

const expectedVersion = computed(() => props.selected.currentMapping?.version ?? null);
const beforeLabel = computed(() => displayValue(props.selected.currentMapping?.canonicalConcept?.name, 'Unmapped'));
const selectedCanonical = computed(() => displayValue(props.canonicalOptions.find((option) => option.code === canonicalConcept.value)?.name, 'Select a canonical concept'));

function statusTone(value: string): StatusBadgeTone {
    if (value === 'APPROVED') return 'success';
    if (value === 'REVIEW_REQUIRED' || value === 'DRAFT') return 'warning';
    if (value === 'REJECTED') return 'danger';
    return 'neutral';
}

function submit(): void {
    if (props.submitting || canonicalConcept.value.trim() === '' || rationale.value.trim() === '') return;
    emit('submit', {
        sourceConcept: props.selected.sourceConcept,
        entryPoint: props.selected.entryPoint,
        canonicalConcept: canonicalConcept.value,
        allowedScope: allowedScope.value.split(',').map((value) => value.trim()).filter(Boolean),
        periodType: periodType.value,
        signConvention: signConvention.value.trim() || null,
        status: status.value,
        rationale: rationale.value.trim(),
        evidenceIds: evidenceIds.value.split(',').map((value) => value.trim()).filter(Boolean),
        expectedVersion: expectedVersion.value,
    });
}
</script>

<template>
    <OpsDialog title-id="create-mapping-version-title" description-id="create-mapping-version-description" panel-class="max-w-3xl" @close="emit('close')">
        <header class="flex items-start justify-between gap-4 border-b border-hissa-border pb-4">
            <div class="min-w-0">
                <h2 id="create-mapping-version-title" class="ops-section-title">Save mapping version</h2>
                <p id="create-mapping-version-description" class="mt-1 text-sm text-hissa-secondary">Append a new source-to-target rule for this mapping series.</p>
                <p class="ops-technical ops-wrap mt-2 text-hissa-secondary" :title="displayValue(selected.mappingSeriesKey)">{{ displayValue(selected.mappingSeriesKey) }}</p>
            </div>
            <button type="button" aria-label="Close save mapping version" class="min-h-10 shrink-0 rounded-md border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-secondary outline-none transition-colors hover:bg-hissa-surface-subtle focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" :disabled="submitting" @click="emit('close')">Close</button>
        </header>

        <section class="mt-5 border-y border-hissa-border" aria-label="Mapping version preview">
            <div class="grid gap-0 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]">
                <div class="min-w-0 px-3 py-3 sm:px-4">
                    <p class="ops-meta text-hissa-secondary">Current target</p>
                    <p class="ops-wrap mt-1 font-semibold text-hissa-primary" :title="beforeLabel">{{ beforeLabel }}</p>
                    <p class="mt-1 ops-meta text-hissa-secondary">{{ expectedVersion === null ? 'No persisted version' : `Version ${expectedVersion}` }}</p>
                </div>
                <div class="flex items-center border-y border-hissa-border px-3 py-2 text-center text-sm font-semibold text-hissa-secondary sm:border-y-0 sm:border-x">next version</div>
                <div class="min-w-0 px-3 py-3 sm:px-4">
                    <p class="ops-meta text-hissa-secondary">New target preview</p>
                    <p class="ops-wrap mt-1 font-semibold text-hissa-primary" :title="selectedCanonical">{{ selectedCanonical }}</p>
                    <p class="mt-1 flex items-center gap-2 ops-meta text-hissa-secondary"><span>{{ expectedVersion === null ? 'v1' : `v${expectedVersion + 1}` }}</span><StatusBadge :label="humanize(status)" :tone="statusTone(status)" /></p>
                </div>
            </div>
        </section>

        <form class="mt-5 space-y-4" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <label for="mapping-canonical-concept" class="grid gap-1.5 text-[13px] font-semibold leading-[18px] text-hissa-primary">
                    <span>Canonical concept</span>
                    <select id="mapping-canonical-concept" v-model="canonicalConcept" required class="min-h-10 w-full rounded-md border border-hissa-border bg-hissa-surface px-3 py-2 text-sm font-normal text-hissa-primary outline-none transition-colors hover:border-hissa-border-strong focus-visible:border-hissa-action focus-visible:ring-2 focus-visible:ring-hissa-action/20">
                        <option value="" disabled>Select concept</option>
                        <option v-for="option in canonicalOptions" :key="option.code" :value="option.code">{{ displayValue(option.code, 'Unknown code') }} · {{ displayValue(option.name, 'Unknown concept') }}</option>
                    </select>
                </label>
                <label for="mapping-version-status" class="grid gap-1.5 text-[13px] font-semibold leading-[18px] text-hissa-primary">
                    <span>Status</span>
                    <select id="mapping-version-status" v-model="status" class="min-h-10 w-full rounded-md border border-hissa-border bg-hissa-surface px-3 py-2 text-sm font-normal text-hissa-primary outline-none transition-colors hover:border-hissa-border-strong focus-visible:border-hissa-action focus-visible:ring-2 focus-visible:ring-hissa-action/20">
                        <option value="DRAFT">Draft</option>
                        <option value="APPROVED">Approved</option>
                        <option value="REVIEW_REQUIRED">Review required</option>
                        <option value="REJECTED">Rejected</option>
                    </select>
                </label>
                <label for="mapping-period-type" class="grid gap-1.5 text-[13px] font-semibold leading-[18px] text-hissa-primary">
                    <span>Period type</span>
                    <select id="mapping-period-type" v-model="periodType" class="min-h-10 w-full rounded-md border border-hissa-border bg-hissa-surface px-3 py-2 text-sm font-normal text-hissa-primary outline-none transition-colors hover:border-hissa-border-strong focus-visible:border-hissa-action focus-visible:ring-2 focus-visible:ring-hissa-action/20">
                        <option :value="null">Any period</option>
                        <option value="INSTANT">Instant</option>
                        <option value="DURATION">Duration</option>
                    </select>
                </label>
                <label for="mapping-sign-convention" class="grid gap-1.5 text-[13px] font-semibold leading-[18px] text-hissa-primary">
                    <span>Sign convention</span>
                    <input id="mapping-sign-convention" v-model="signConvention" type="text" placeholder="AS_REPORTED" class="min-h-10 w-full rounded-md border border-hissa-border bg-hissa-surface px-3 py-2 text-sm font-normal text-hissa-primary outline-none placeholder:text-hissa-muted transition-colors hover:border-hissa-border-strong focus-visible:border-hissa-action focus-visible:ring-2 focus-visible:ring-hissa-action/20" />
                </label>
            </div>
            <label for="mapping-allowed-scope" class="grid gap-1.5 text-[13px] font-semibold leading-[18px] text-hissa-primary">
                <span>Allowed scopes <span class="font-normal text-hissa-secondary">(comma separated)</span></span>
                <input id="mapping-allowed-scope" v-model="allowedScope" type="text" placeholder="CONSOLIDATED, STANDALONE" class="min-h-10 w-full rounded-md border border-hissa-border bg-hissa-surface px-3 py-2 text-sm font-normal text-hissa-primary outline-none placeholder:text-hissa-muted transition-colors hover:border-hissa-border-strong focus-visible:border-hissa-action focus-visible:ring-2 focus-visible:ring-hissa-action/20" />
            </label>
            <label for="mapping-evidence-ids" class="grid gap-1.5 text-[13px] font-semibold leading-[18px] text-hissa-primary">
                <span>Evidence IDs <span class="font-normal text-hissa-secondary">(comma separated)</span></span>
                <input id="mapping-evidence-ids" v-model="evidenceIds" type="text" placeholder="EVID-001" class="min-h-10 w-full rounded-md border border-hissa-border bg-hissa-surface px-3 py-2 text-sm font-normal text-hissa-primary outline-none placeholder:text-hissa-muted transition-colors hover:border-hissa-border-strong focus-visible:border-hissa-action focus-visible:ring-2 focus-visible:ring-hissa-action/20" />
            </label>
            <label for="mapping-rationale" class="grid gap-1.5 text-[13px] font-semibold leading-[18px] text-hissa-primary">
                <span>Rationale <span class="font-normal text-hissa-secondary">(required)</span></span>
                <textarea id="mapping-rationale" v-model="rationale" required rows="4" class="min-h-24 w-full resize-y rounded-md border border-hissa-border bg-hissa-surface px-3 py-2 text-sm font-normal text-hissa-primary outline-none placeholder:text-hissa-muted transition-colors hover:border-hissa-border-strong focus-visible:border-hissa-action focus-visible:ring-2 focus-visible:ring-hissa-action/20" placeholder="Explain the evidence and decision behind this mapping version." />
            </label>

            <div class="border-l border-hissa-info bg-hissa-info-soft px-3 py-2.5 text-sm text-hissa-info" role="status">Saving a mapping version appends a new rule. It does not reprocess filings.</div>
            <p v-if="error" class="border-l border-hissa-danger bg-hissa-danger-soft px-3 py-2.5 text-sm text-hissa-danger" role="alert">{{ error }}</p>
            <div class="flex flex-wrap justify-end gap-2 border-t border-hissa-border pt-4">
                <button type="button" class="min-h-10 rounded-md border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-secondary outline-none transition-colors hover:bg-hissa-surface-subtle focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60" :disabled="submitting" @click="emit('close')">Cancel</button>
                <button type="submit" class="min-h-10 rounded-md bg-hissa-action px-3 py-2 text-sm font-semibold text-white outline-none transition-colors hover:bg-hissa-action-hover focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60" :disabled="submitting || canonicalConcept === '' || rationale.trim() === ''" :aria-busy="submitting">{{ submitting ? 'Saving…' : 'Save version' }}</button>
            </div>
        </form>
    </OpsDialog>
</template>
