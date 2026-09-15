<script setup lang="ts">
import { computed, ref } from 'vue';

import OpsDialog from '../../../components/ops/OpsDialog.vue';
import { OpsHttpError } from '../api/pipelineService';
import { usePipelineActions } from '../composables/usePipelineActions';
import { PIPELINE_STAGE_KEYS, type PipelineStageKey } from '../types/pipeline';

const props = defineProps<{ filingId: string; initialStage?: string }>();
const emit = defineEmits<{ close: []; accepted: [] }>();
const stage = ref<PipelineStageKey | ''>(PIPELINE_STAGE_KEYS.includes(props.initialStage as PipelineStageKey) ? props.initialStage as PipelineStageKey : '');
const reason = ref('');
const actions = usePipelineActions();
const errorMessage = computed(() => actions.reprocess.error.value instanceof OpsHttpError ? actions.reprocess.error.value.message : actions.reprocess.error.value?.message ?? null);

async function submit(): Promise<void> {
    const selectedStage = stage.value;
    if (actions.reprocess.isPending.value || selectedStage === '' || reason.value.trim().length < 3) return;
    await actions.reprocess.mutateAsync({ filingId: props.filingId, stage: selectedStage, reason: reason.value.trim() });
    emit('accepted');
}
</script>

<template>
    <OpsDialog title-id="reprocess-title" description-id="reprocess-description" @close="emit('close')">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 id="reprocess-title" class="ops-section-title">Reprocess filing</h2>
            </div>
            <span class="ops-technical max-w-[11rem] truncate text-hissa-secondary" :title="filingId">{{ filingId }}</span>
        </div>
        <p id="reprocess-description" class="mt-3 text-sm leading-5 text-hissa-secondary">Reprocess creates a new pipeline run. It is different from retrying a failed attempt.</p>
        <div class="mt-4 border-l border-hissa-danger bg-hissa-danger-soft px-3 py-2.5 text-sm text-hissa-danger">
            Select the first stage that should run again and document why the filing needs a new run. This action may affect downstream processing results.
        </div>
        <label class="mt-4 block text-[13px] font-semibold text-hissa-primary" for="reprocess-stage">Start stage</label>
        <select id="reprocess-stage" v-model="stage" class="mt-1.5 min-h-10 w-full rounded-md border border-hissa-border bg-hissa-surface px-3 py-2 text-sm text-hissa-primary outline-none transition-colors hover:border-hissa-border-strong focus-visible:border-hissa-action focus-visible:ring-2 focus-visible:ring-hissa-action/20">
            <option value="" disabled>Select a start stage</option>
            <option v-for="option in PIPELINE_STAGE_KEYS" :key="option" :value="option">{{ option }}</option>
        </select>
        <label class="mt-4 block text-[13px] font-semibold text-hissa-primary" for="reprocess-reason">Reason <span class="font-normal text-hissa-secondary">(required)</span></label>
        <textarea id="reprocess-reason" v-model="reason" rows="3" class="mt-1.5 min-h-24 w-full resize-y rounded-md border border-hissa-border bg-hissa-surface px-3 py-2 text-sm text-hissa-primary outline-none placeholder:text-hissa-muted transition-colors hover:border-hissa-border-strong focus-visible:border-hissa-action focus-visible:ring-2 focus-visible:ring-hissa-action/20" placeholder="Describe the correction or source of the issue" />
        <p class="mt-1 text-xs text-hissa-secondary">Enter at least 3 characters so the operation is auditable.</p>
        <p v-if="errorMessage !== null" role="alert" class="ops-wrap mt-3 border-l border-hissa-danger bg-hissa-danger-soft px-3 py-2 text-sm text-hissa-danger">{{ errorMessage }}</p>
        <div class="mt-5 flex flex-wrap justify-end gap-2 border-t border-hissa-border pt-4">
            <button type="button" class="min-h-10 rounded-md border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-secondary outline-none transition-colors hover:bg-hissa-surface-subtle focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60" :disabled="actions.reprocess.isPending.value" @click="emit('close')">Cancel</button>
            <button type="button" class="min-h-10 rounded-md bg-hissa-danger px-3 py-2 text-sm font-semibold text-white outline-none transition-opacity hover:opacity-90 focus-visible:ring-2 focus-visible:ring-hissa-danger focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60" :disabled="actions.reprocess.isPending.value || stage === '' || reason.trim().length < 3" :aria-busy="actions.reprocess.isPending.value" @click="submit">{{ actions.reprocess.isPending.value ? 'Starting…' : 'Start reprocess' }}</button>
        </div>
    </OpsDialog>
</template>
