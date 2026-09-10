<script setup lang="ts">
import { computed, ref } from 'vue';
import { OpsHttpError } from '../api/pipelineService';
import { usePipelineActions } from '../composables/usePipelineActions';
import { PIPELINE_STAGE_KEYS } from '../types/pipeline';

const props = defineProps<{ filingId: string; initialStage?: string }>();
const emit = defineEmits<{ close: []; accepted: [] }>();
const stage = ref(PIPELINE_STAGE_KEYS.includes(props.initialStage as (typeof PIPELINE_STAGE_KEYS)[number]) ? props.initialStage as (typeof PIPELINE_STAGE_KEYS)[number] : 'DOWNLOAD');
const reason = ref('');
const actions = usePipelineActions();
const errorMessage = computed(() => actions.reprocess.error.value instanceof OpsHttpError ? actions.reprocess.error.value.message : actions.reprocess.error.value?.message ?? null);

async function submit(): Promise<void> {
    if (reason.value.trim().length < 3) return;
    await actions.reprocess.mutateAsync({ filingId: props.filingId, stage: stage.value, reason: reason.value.trim() });
    emit('accepted');
}
</script>

<template>
    <div class="fixed inset-0 z-[60] grid place-items-center bg-black/30 p-4" role="presentation">
        <section role="dialog" aria-modal="true" aria-labelledby="reprocess-title" class="w-full max-w-md rounded-xl bg-hissa-surface p-6 shadow-xl">
            <h2 id="reprocess-title" class="text-lg font-semibold">Reprocess filing</h2>
            <p class="mt-2 text-sm text-hissa-secondary">Reprocess creates a new pipeline run. It is different from retrying a failed attempt.</p>
            <label class="mt-4 block text-sm font-semibold" for="reprocess-stage">Start stage</label>
            <select id="reprocess-stage" v-model="stage" class="mt-1 w-full rounded-lg border border-hissa-border p-2 text-sm">
                <option v-for="option in PIPELINE_STAGE_KEYS" :key="option" :value="option">{{ option }}</option>
            </select>
            <label class="mt-3 block text-sm font-semibold" for="reprocess-reason">Reason</label>
            <textarea id="reprocess-reason" v-model="reason" rows="3" class="mt-1 w-full rounded-lg border border-hissa-border p-2 text-sm" />
            <p v-if="errorMessage !== null" role="alert" class="mt-2 text-sm text-hissa-danger">{{ errorMessage }}</p>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" class="rounded-lg border border-hissa-border px-3 py-2 text-sm" :disabled="actions.reprocess.isPending.value" @click="emit('close')">Cancel</button>
                <button type="button" class="rounded-lg bg-hissa-action px-3 py-2 text-sm font-semibold text-white" :disabled="actions.reprocess.isPending.value || reason.trim().length < 3" @click="submit">{{ actions.reprocess.isPending.value ? 'Starting…' : 'Start reprocess' }}</button>
            </div>
        </section>
    </div>
</template>
