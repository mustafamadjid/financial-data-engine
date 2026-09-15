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
    await actions.retry.mutateAsync({ jobRunId: props.jobRunId, reason: reason.value });
    emit('accepted');
}
</script>

<template>
    <OpsDialog title-id="retry-title" description-id="retry-description" @close="emit('close')">
        <h2 id="retry-title" class="text-lg font-semibold leading-[26px]">Retry {{ stage }} stage?</h2>
        <p id="retry-description" class="mt-2 text-sm leading-[21px] text-hissa-secondary">This retries filing {{ filingId }} using the existing pipeline run and a new attempt.</p>
        <label class="mt-4 block text-sm font-semibold" for="retry-reason">Reason (optional)</label>
        <textarea id="retry-reason" v-model="reason" rows="3" class="mt-1 w-full rounded-lg border border-hissa-border p-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-hissa-action" />
        <p v-if="errorMessage !== null" role="alert" class="mt-2 text-sm text-hissa-danger">{{ errorMessage }}</p>
        <div class="mt-5 flex justify-end gap-2">
            <button type="button" class="min-h-10 rounded-lg border border-hissa-border px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" :disabled="actions.retry.isPending.value" @click="emit('close')">Cancel</button>
            <button type="button" class="min-h-10 rounded-lg bg-hissa-action px-3 py-2 text-sm font-semibold text-white outline-none focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" :disabled="actions.retry.isPending.value" @click="submit">{{ actions.retry.isPending.value ? 'Retrying…' : 'Retry stage' }}</button>
        </div>
    </OpsDialog>
</template>
