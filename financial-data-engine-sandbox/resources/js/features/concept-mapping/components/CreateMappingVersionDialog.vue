<script setup lang="ts">
import { computed, ref } from 'vue';

import OpsDialog from '../../../components/ops/OpsDialog.vue';
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

const canonicalConcept = ref(props.selected.currentMapping?.canonicalConcept.code ?? '');
const status = ref<MappingStatus>(props.selected.currentMapping?.status ?? 'DRAFT');
const rationale = ref('');
const periodType = ref<'INSTANT' | 'DURATION' | null>(props.selected.currentMapping?.periodType === 'DURATION' ? 'DURATION' : props.selected.currentMapping?.periodType === 'INSTANT' ? 'INSTANT' : null);
const signConvention = ref(props.selected.currentMapping?.signConvention ?? '');
const allowedScope = ref(props.selected.currentMapping?.allowedScope?.join(', ') ?? '');
const evidenceIds = ref(props.selected.currentMapping?.evidenceIds?.join(', ') ?? '');

const expectedVersion = computed(() => props.selected.currentMapping?.version ?? null);
const beforeLabel = computed(() => props.selected.currentMapping?.canonicalConcept.name ?? 'UNMAPPED');
const selectedCanonical = computed(() => props.canonicalOptions.find((option) => option.code === canonicalConcept.value)?.name ?? 'Select a canonical concept');

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
    <OpsDialog title-id="create-mapping-version-title" panel-class="max-w-3xl" @close="emit('close')">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-wide text-hissa-secondary">Append-only mapping</p>
                <h2 id="create-mapping-version-title" class="mt-1 text-xl font-semibold">Save new version</h2>
                <p class="mt-1 text-sm text-hissa-secondary">{{ selected.sourceConcept }} · {{ selected.entryPoint ?? 'Any / unknown entry point' }}</p>
            </div>
            <button type="button" class="rounded-lg border border-hissa-border px-3 py-2 text-sm" :disabled="submitting" @click="emit('close')">Close</button>
        </div>

        <div class="mt-5 grid gap-3 rounded-lg bg-hissa-subtle p-4 sm:grid-cols-2" aria-label="Mapping before and after preview">
            <div><p class="text-xs uppercase tracking-wide text-hissa-secondary">Current</p><p class="mt-1 font-semibold">{{ beforeLabel }}</p><p class="text-xs text-hissa-secondary">{{ expectedVersion === null ? 'No persisted version' : 'Version ' + expectedVersion }}</p></div>
            <div><p class="text-xs uppercase tracking-wide text-hissa-secondary">New version preview</p><p class="mt-1 font-semibold">{{ selectedCanonical }}</p><p class="text-xs text-hissa-secondary">{{ status }} · next append-only version</p></div>
        </div>

        <form class="mt-5 space-y-4" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-medium">Canonical concept<select v-model="canonicalConcept" required class="mt-1 min-h-10 w-full rounded-lg border border-hissa-border bg-hissa-surface px-3 text-sm"><option value="" disabled>Select concept</option><option v-for="option in canonicalOptions" :key="option.code" :value="option.code">{{ option.code }} · {{ option.name }}</option></select></label>
                <label class="text-sm font-medium">Status<select v-model="status" class="mt-1 min-h-10 w-full rounded-lg border border-hissa-border bg-hissa-surface px-3 text-sm"><option value="DRAFT">Draft</option><option value="APPROVED">Approved</option><option value="REVIEW_REQUIRED">Review required</option><option value="REJECTED">Rejected</option></select></label>
                <label class="text-sm font-medium">Period type<select v-model="periodType" class="mt-1 min-h-10 w-full rounded-lg border border-hissa-border bg-hissa-surface px-3 text-sm"><option :value="null">Any period</option><option value="INSTANT">Instant</option><option value="DURATION">Duration</option></select></label>
                <label class="text-sm font-medium">Sign convention<input v-model="signConvention" type="text" placeholder="AS_REPORTED" class="mt-1 min-h-10 w-full rounded-lg border border-hissa-border bg-hissa-surface px-3 text-sm"></label>
            </div>
            <label class="block text-sm font-medium">Allowed scopes <span class="font-normal text-hissa-secondary">(comma separated)</span><input v-model="allowedScope" type="text" placeholder="CONSOLIDATED, STANDALONE" class="mt-1 min-h-10 w-full rounded-lg border border-hissa-border bg-hissa-surface px-3 text-sm"></label>
            <label class="block text-sm font-medium">Evidence IDs <span class="font-normal text-hissa-secondary">(comma separated)</span><input v-model="evidenceIds" type="text" placeholder="EVID-001" class="mt-1 min-h-10 w-full rounded-lg border border-hissa-border bg-hissa-surface px-3 text-sm"></label>
            <label class="block text-sm font-medium">Rationale<textarea v-model="rationale" required rows="3" class="mt-1 w-full rounded-lg border border-hissa-border bg-hissa-surface px-3 py-2 text-sm" placeholder="Explain why this mapping version is needed."></textarea></label>

            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">Saving a mapping version only records a new rule. It does not reprocess filings.</div>
            <p v-if="error" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700" role="alert">{{ error }}</p>
            <div class="flex justify-end gap-2"><button type="button" class="rounded-lg border border-hissa-border px-4 py-2 text-sm font-semibold" :disabled="submitting" @click="emit('close')">Cancel</button><button type="submit" class="rounded-lg bg-hissa-action px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50" :disabled="submitting || canonicalConcept === '' || rationale.trim() === ''">{{ submitting ? 'Saving…' : 'Save version' }}</button></div>
        </form>
    </OpsDialog>
</template>
