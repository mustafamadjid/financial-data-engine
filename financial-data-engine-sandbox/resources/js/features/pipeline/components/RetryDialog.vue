<script setup lang="ts">
import { computed, ref } from 'vue';
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
    <div class="fixed inset-0 z-50 grid place-items-center bg-black/30 p-4" role="presentation">
        <section role="dialog" aria-modal="true" aria-labelledby="retry-title" class="w-full max-w-md rounded-xl bg-hissa-surface p-6 shadow-xl">
            <h2 id="retry-title" class="text-lg font-semibold">Retry {{ stage }} stage?</h2>
            <p class="mt-2 text-sm text-hissa-secondary">This retries filing {{ filingId }} using the existing pipeline run and a new attempt.</p>
            <label class="mt-4 block text-sm font-semibold" for="retry-reason">Reason (optional)</label>
            <textarea id="retry-reason" v-model="reason" rows="3" class="mt-1 w-full rounded-lg border border-hissa-border p-2 text-sm" />
            <p v-if="errorMessage !== null" role="alert" class="mt-2 text-sm text-hissa-danger">{{ errorMessage }}</p>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" class="rounded-lg border border-hissa-border px-3 py-2 text-sm" :disabled="actions.retry.isPending.value" @click="emit('close')">Cancel</button>
                <button type="button" class="rounded-lg bg-hissa-action px-3 py-2 text-sm font-semibold text-white" :disabled="actions.retry.isPending.value" @click="submit">{{ actions.retry.isPending.value ? 'Retrying…' : 'Retry stage' }}</button>
            </div>
        </section>
    </div>
</template>
