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
                <h2 id="mark-review-title" class="ops-section-title">Mark for review</h2>
            </div>
            <button type="button" aria-label="Close mark for review" class="min-h-10 shrink-0 rounded-md border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-secondary outline-none transition-colors hover:bg-hissa-surface-subtle focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="emit('close')">Close</button>
        </div>
        <p id="mark-review-description" class="mt-1 text-sm leading-5 text-hissa-secondary">This creates a review item without changing the financial record or reprocessing data.</p>
        <div v-if="!capability.allowed" class="mt-5 border-l border-hissa-border bg-hissa-surface-subtle px-3 py-2.5 text-sm" role="status">
            <p class="font-semibold text-hissa-primary">Review action unavailable</p>
            <p class="ops-wrap mt-1 text-hissa-secondary">{{ capability.reason ?? 'This item is not eligible for manual review.' }}</p>
        </div>
        <form v-else class="mt-5 space-y-4" @submit.prevent="submit">
            <div>
                <label for="review-rationale" class="grid gap-1.5 text-[13px] font-semibold leading-[18px] text-hissa-primary">Rationale</label>
                <textarea id="review-rationale" v-model="rationale" rows="4" class="mt-1.5 min-h-24 w-full resize-y rounded-md border border-hissa-border bg-hissa-surface px-3 py-2 text-sm text-hissa-primary outline-none placeholder:text-hissa-muted transition-colors hover:border-hissa-border-strong focus-visible:border-hissa-action focus-visible:ring-2 focus-visible:ring-hissa-action/20" :aria-invalid="validationMessage !== null ? 'true' : undefined" aria-describedby="review-rationale-help review-rationale-error" />
                <p id="review-rationale-help" class="mt-1 ops-meta text-hissa-secondary">Describe what an analyst should verify.</p>
                <p v-if="validationMessage" id="review-rationale-error" class="mt-1 text-sm text-hissa-danger" role="alert">{{ validationMessage }}</p>
            </div>
            <p v-if="errorMessage" class="ops-wrap border-l border-hissa-danger bg-hissa-danger-soft px-3 py-2.5 text-sm text-hissa-danger" role="alert">{{ errorMessage }}</p>
            <p aria-live="polite" class="ops-meta text-hissa-secondary">{{ mutation.isPending.value ? 'Saving review item…' : mutation.isSuccess.value ? 'Review item accepted.' : '' }}</p>
            <div class="flex flex-wrap justify-end gap-2 border-t border-hissa-border pt-4">
                <button type="button" class="min-h-10 rounded-md border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-secondary outline-none transition-colors hover:bg-hissa-surface-subtle focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60" :disabled="mutation.isPending.value" @click="emit('close')">Cancel</button>
                <button type="submit" class="min-h-10 rounded-md bg-hissa-action px-3 py-2 text-sm font-semibold text-white outline-none transition-colors hover:bg-hissa-action-hover focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60" :disabled="mutation.isPending.value || validationMessage !== null" :aria-busy="mutation.isPending.value">{{ mutation.isPending.value ? 'Saving…' : 'Mark for review' }}</button>
            </div>
        </form>
    </OpsDialog>
</template>
