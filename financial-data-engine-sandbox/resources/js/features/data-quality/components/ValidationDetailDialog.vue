<script setup lang="ts">
import { ref } from 'vue';

import OpsDialog from '../../../components/ops/OpsDialog.vue';
import AsyncState from '../../../components/ops/AsyncState.vue';
import MarkReviewDialog from '../../review-items/components/MarkReviewDialog.vue';
import type { ValidationDetail } from '../types/dataQuality';

const props = defineProps<{ detail?: ValidationDetail; loading: boolean; error: string | null }>();
const emit = defineEmits<{ close: []; retry: [] }>();
const reviewDialogOpen = ref(false);
</script>

<template>
    <OpsDialog title-id="validation-detail-title" panel-class="max-w-3xl" @close="emit('close')">
        <button v-if="detail" type="button" class="mb-4 min-h-10 rounded-lg border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-action" @click="reviewDialogOpen = true">Mark for review</button>
        <div class="flex items-start justify-between gap-4"><div><p class="text-xs font-semibold uppercase tracking-wide text-hissa-action">Data Quality</p><h2 id="validation-detail-title" class="mt-1 text-xl font-semibold">Validation detail</h2></div><button type="button" aria-label="Close detail" class="rounded-lg px-2 py-1 text-2xl leading-none text-hissa-secondary hover:bg-hissa-subtle" @click="emit('close')">×</button></div>
        <AsyncState v-if="loading" state="loading" title="Loading validation detail" message="Fetching the selected version-scoped result." />
        <AsyncState v-else-if="error" state="error" title="Validation detail could not be loaded" :message="error"><template #action><button type="button" class="rounded-lg bg-hissa-action px-3 py-2 text-sm font-semibold text-white" @click="emit('retry')">Try again</button></template></AsyncState>
        <div v-else-if="detail" class="mt-5 max-h-[68vh] space-y-5 overflow-y-auto pr-1"><section class="grid gap-3 rounded-lg bg-hissa-subtle p-4 sm:grid-cols-3"><div><dt class="text-xs text-hissa-secondary">Result</dt><dd class="mt-1 font-semibold">{{ detail.result }}</dd></div><div><dt class="text-xs text-hissa-secondary">Severity</dt><dd class="mt-1 font-semibold">{{ detail.severity }}</dd></div><div><dt class="text-xs text-hissa-secondary">Rule</dt><dd class="mt-1 font-mono text-xs">{{ detail.rule.code }} · v{{ detail.rule.version }}</dd></div></section><section><h3 class="font-semibold">Message</h3><p class="mt-2 text-sm text-hissa-secondary">{{ detail.message ?? 'No message provided.' }}</p></section><section><h3 class="font-semibold">Input facts</h3><ul class="mt-2 space-y-2"><li v-for="fact in detail.inputFacts" :key="fact.normalizedFactId" class="rounded-lg border border-hissa-border p-3 text-sm"><a :href="fact.href" class="font-mono text-xs text-hissa-action underline">{{ fact.normalizedFactId }}</a><p class="mt-1">{{ fact.canonicalConcept }} · {{ fact.value ?? 'Unknown' }} {{ fact.currency ?? '' }}</p><p class="text-xs text-hissa-secondary">{{ fact.sourceConcept }}</p></li><li v-if="detail.inputFacts.length === 0" class="text-sm text-hissa-secondary">No input facts are linked to this result.</li></ul></section></div>
        <MarkReviewDialog v-if="reviewDialogOpen && props.detail" :open="reviewDialogOpen" entity-type="validation_result" :entity-id="props.detail.validationResultId" :filing-id="props.detail.filingId" :expected-version="props.detail.reviewVersion" :capability="props.detail.allowedActions.markForReview" @close="reviewDialogOpen = false" @accepted="reviewDialogOpen = false" />
    </OpsDialog>
</template>
