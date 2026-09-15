<script setup lang="ts">
import { computed, ref } from 'vue';

import OpsDialog from '../../../components/ops/OpsDialog.vue';
import { useMarkReviewItem } from '../queries/useMarkReviewItem';
import type { ReviewItemCapability, ReviewEntityType } from '../types/reviewItem';

const props = defineProps<{
    open: boolean;
    entityType: ReviewEntityType;
    entityId: string;
    filingId: string;
    expectedVersion: string;
    capability: ReviewItemCapability;
}>();

const emit = defineEmits<{
    close: [];
    accepted: [];
}>();

const rationale = ref('');
const mutation = useMarkReviewItem();
const validationMessage = computed(() => rationale.value.trim() === '' ? 'Enter a rationale before marking this item.' : null);
const errorMessage = computed(() => mutation.error.value instanceof Error ? mutation.error.value.message : null);

async function submit(): Promise<void> {
    if (!props.capability.allowed || validationMessage.value !== null || mutation.isPending.value) return;

    await mutation.mutateAsync({
        entityType: props.entityType,
        entityId: props.entityId,
        filingId: props.filingId,
        expectedVersion: props.expectedVersion,
        rationale: rationale.value.trim(),
        idempotencyKey: 'review-' + props.entityType + '-' + props.entityId,
    });
    emit('accepted');
}
</script>

<template>
    <OpsDialog v-if="open" title-id="mark-review-title" description-id="mark-review-description" panel-class="max-w-lg" @close="emit('close')">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-hissa-action">Manual review</p>
                <h2 id="mark-review-title" class="mt-1 text-xl font-semibold">Mark for review</h2>
            </div>
            <button type="button" aria-label="Close mark for review" class="min-h-10 min-w-10 rounded-lg px-2 py-1 text-2xl leading-none text-hissa-secondary hover:bg-hissa-subtle" @click="emit('close')">×</button>
        </div>
        <p id="mark-review-description" class="mt-3 text-sm leading-[21px] text-hissa-secondary">This creates a review item without changing the financial record or reprocessing data.</p>
        <div v-if="!capability.allowed" class="mt-5 rounded-lg border border-hissa-border bg-hissa-subtle p-4 text-sm" role="status">
            <p class="font-semibold">Review action unavailable</p>
            <p class="mt-1 text-hissa-secondary">{{ capability.reason ?? 'This item is not eligible for manual review.' }}</p>
        </div>
        <form v-else class="mt-5 space-y-4" @submit.prevent="submit">
            <div>
                <label for="review-rationale" class="text-sm font-semibold">Rationale</label>
                <textarea id="review-rationale" v-model="rationale" rows="4" class="mt-1 w-full rounded-lg border border-hissa-border bg-hissa-surface p-3 text-sm outline-none focus:border-hissa-action focus:ring-2 focus:ring-hissa-action/20" :aria-invalid="validationMessage !== null ? 'true' : undefined" aria-describedby="review-rationale-help review-rationale-error" />
                <p id="review-rationale-help" class="mt-1 text-xs text-hissa-secondary">Describe what an analyst should verify.</p>
                <p v-if="validationMessage" id="review-rationale-error" class="mt-1 text-sm text-hissa-danger" role="alert">{{ validationMessage }}</p>
            </div>
            <p v-if="errorMessage" class="text-sm text-hissa-danger" role="alert">{{ errorMessage }}</p>
            <p aria-live="polite" class="text-sm text-hissa-secondary">{{ mutation.isPending.value ? 'Saving review item…' : mutation.isSuccess.value ? 'Review item accepted.' : '' }}</p>
            <div class="flex justify-end gap-2">
                <button type="button" class="min-h-10 rounded-lg border border-hissa-border px-4 py-2 text-sm font-semibold" @click="emit('close')">Cancel</button>
                <button type="submit" class="min-h-10 rounded-lg bg-hissa-action px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50" :disabled="mutation.isPending.value || validationMessage !== null">{{ mutation.isPending.value ? 'Saving…' : 'Mark for review' }}</button>
            </div>
        </form>
    </OpsDialog>
</template>
