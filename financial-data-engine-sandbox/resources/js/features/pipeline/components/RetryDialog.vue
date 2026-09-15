<script setup lang="ts">
import { computed, ref } from 'vue';

import OpsDialog from '../../../components/ops/OpsDialog.vue';
import { OpsHttpError } from '../api/pipelineService';
import { usePipelineActions } from '../composables/usePipelineActions';

const props = defineProps<{ filingId: string; jobRunId: number; stage: string }>();
const emit = defineEmits<{ close: []; accepted: [] }>();
const reason = ref('');
const actions = usePipelineActions();
const errorMessage = computed(() => actions.retry.error.value instanceof OpsHttpError ? actions.retry.error.value.message : actions.retry.error.value?.message ?? null);

async function submit(): Promise<void> {
    if (actions.retry.isPending.value) return;
    await actions.retry.mutateAsync({ jobRunId: props.jobRunId, reason: reason.value });
    emit('accepted');
}
</script>

<template>
    <OpsDialog title-id="retry-title" description-id="retry-description" @close="emit('close')">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 id="retry-title" class="ops-section-title">Retry {{ stage }} stage?</h2>
            </div>
            <span class="ops-technical text-hissa-secondary">Run {{ jobRunId }}</span>
        </div>
        <p id="retry-description" class="mt-3 text-sm leading-5 text-hissa-secondary">This starts a new attempt for filing <span class="ops-technical text-hissa-primary">{{ filingId }}</span> using the existing pipeline run.</p>
        <div class="mt-4 border-l border-hissa-warning bg-hissa-warning-soft px-3 py-2.5 text-sm text-hissa-warning">
            Confirm the failed stage before retrying. A retry creates another execution attempt and is recorded in the audit history.
        </div>
        <label class="mt-4 block text-[13px] font-semibold text-hissa-primary" for="retry-reason">Reason <span class="font-normal text-hissa-secondary">(optional)</span></label>
        <textarea id="retry-reason" v-model="reason" rows="3" class="mt-1.5 min-h-24 w-full resize-y rounded-md border border-hissa-border bg-hissa-surface px-3 py-2 text-sm text-hissa-primary outline-none placeholder:text-hissa-muted transition-colors hover:border-hissa-border-strong focus-visible:border-hissa-action focus-visible:ring-2 focus-visible:ring-hissa-action/20" placeholder="Add context for the next operator" />
        <p v-if="errorMessage !== null" role="alert" class="ops-wrap mt-3 border-l border-hissa-danger bg-hissa-danger-soft px-3 py-2 text-sm text-hissa-danger">{{ errorMessage }}</p>
        <div class="mt-5 flex flex-wrap justify-end gap-2 border-t border-hissa-border pt-4">
            <button type="button" class="min-h-10 rounded-md border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-secondary outline-none transition-colors hover:bg-hissa-surface-subtle focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60" :disabled="actions.retry.isPending.value" @click="emit('close')">Cancel</button>
            <button type="button" class="min-h-10 rounded-md bg-hissa-action px-3 py-2 text-sm font-semibold text-white outline-none transition-colors hover:bg-hissa-action-hover focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60" :disabled="actions.retry.isPending.value" :aria-busy="actions.retry.isPending.value" @click="submit">{{ actions.retry.isPending.value ? 'Retrying…' : 'Retry stage' }}</button>
        </div>
    </OpsDialog>
</template>
