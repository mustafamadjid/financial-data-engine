<script setup lang="ts">
import { computed, ref } from 'vue';

import AsyncState from '../../../components/ops/AsyncState.vue';
import OpsDialog from '../../../components/ops/OpsDialog.vue';
import StatusBadge, { type StatusBadgeTone } from '../../../components/ops/StatusBadge.vue';
import { displayValue, formatDateTime, humanize } from '../../ops/utils/formatters';
import MarkReviewDialog from '../../review-items/components/MarkReviewDialog.vue';
import type { ValidationDetail, ValidationResult, ValidationSeverity } from '../types/dataQuality';

const props = defineProps<{
    validationResultId?: string;
    detail?: ValidationDetail;
    loading: boolean;
    error: string | null;
}>();

const emit = defineEmits<{ close: []; retry: [] }>();
const reviewDialogOpen = ref(false);
const selectedIdentifier = computed(() => props.detail?.validationResultId ?? props.validationResultId ?? 'Selected result');
const reviewCapability = computed(() => props.detail?.allowedActions?.markForReview ?? { allowed: false, reason: 'Review capability was not returned.' });

function resultLabel(value: ValidationResult): string {
    return value === 'REVIEW_REQUIRED' ? 'Review required' : humanize(value);
}

function severityLabel(value: ValidationSeverity): string {
    return value === 'WARN' ? 'Warning' : humanize(value);
}

function resultTone(value: ValidationResult): StatusBadgeTone {
    if (value === 'PASS') return 'success';
    if (value === 'FAIL') return 'danger';
    if (value === 'REVIEW_REQUIRED') return 'warning';
    return 'neutral';
}

function severityTone(value: ValidationSeverity): StatusBadgeTone {
    if (value === 'ERROR') return 'danger';
    if (value === 'WARN') return 'warning';
    if (value === 'INFO') return 'info';
    return 'neutral';
}

</script>

<template>
    <OpsDialog v-if="!reviewDialogOpen" title-id="validation-detail-title" description-id="validation-detail-description" panel-class="max-w-3xl" @close="emit('close')">
        <header class="flex items-start justify-between gap-4 border-b border-hissa-border pb-4">
            <div class="min-w-0">
                <h2 id="validation-detail-title" class="ops-section-title">Validation detail</h2>
                <p id="validation-detail-description" class="mt-1 text-sm text-hissa-secondary">Version-scoped evidence for the selected validation result.</p>
                <p class="ops-technical ops-wrap mt-2 text-hissa-secondary" :title="displayValue(selectedIdentifier)">{{ displayValue(selectedIdentifier) }}</p>
            </div>
            <button type="button" aria-label="Close validation detail" class="min-h-10 shrink-0 rounded-md border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-secondary outline-none transition-colors hover:bg-hissa-surface-subtle focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="emit('close')">Close</button>
        </header>

        <AsyncState v-if="loading" embedded state="loading" title="Loading validation detail" message="Fetching the selected version-scoped result." />
        <AsyncState v-else-if="error" embedded state="error" title="Validation detail could not be loaded" :message="error">
            <template #action><button type="button" class="min-h-10 rounded-md bg-hissa-action px-3 py-2 text-sm font-semibold text-white outline-none transition-colors hover:bg-hissa-action-hover focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="emit('retry')">Try again</button></template>
        </AsyncState>
        <div v-else-if="detail" class="mt-5 space-y-5 pr-1">
            <section aria-labelledby="validation-outcome-title" class="border-b border-hissa-border pb-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 id="validation-outcome-title" class="ops-section-title">Outcome</h3>
                        <p class="mt-1 text-sm text-hissa-secondary">The result and severity are evaluated within this execution.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <StatusBadge :label="severityLabel(detail.severity)" :tone="severityTone(detail.severity)" />
                        <StatusBadge :label="resultLabel(detail.result)" :tone="resultTone(detail.result)" />
                    </div>
                </div>
                <dl class="mt-4 grid gap-x-4 gap-y-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="ops-meta text-hissa-secondary">Filing</dt>
                        <dd class="ops-technical mt-0.5 truncate text-hissa-primary" :title="displayValue(detail.filing?.filing_id ?? detail.filingId, 'Unknown filing')">{{ displayValue(detail.filing?.filing_id ?? detail.filingId, 'Unknown filing') }}</dd>
                    </div>
                    <div>
                        <dt class="ops-meta text-hissa-secondary">Issuer / period</dt>
                        <dd class="ops-wrap mt-0.5 font-semibold text-hissa-primary">{{ displayValue(detail.filing?.issuer_code, 'Unknown issuer') }} <span aria-hidden="true">·</span> {{ displayValue(detail.filing?.fiscal_period, 'Period unknown') }}</dd>
                    </div>
                    <div>
                        <dt class="ops-meta text-hissa-secondary">Dataset version</dt>
                        <dd class="ops-technical mt-0.5 text-hissa-primary">{{ displayValue(detail.normalizedDatasetVersion) }}</dd>
                    </div>
                    <div>
                        <dt class="ops-meta text-hissa-secondary">Rule-set version</dt>
                        <dd class="ops-technical mt-0.5 text-hissa-primary">{{ displayValue(detail.validationRuleSetVersion) }}</dd>
                    </div>
                    <div>
                        <dt class="ops-meta text-hissa-secondary">Checked</dt>
                        <dd class="mt-0.5 text-hissa-primary"><time :datetime="detail.checkedAt ?? undefined">{{ formatDateTime(detail.checkedAt) }}</time></dd>
                    </div>
                    <div>
                        <dt class="ops-meta text-hissa-secondary">Revision</dt>
                        <dd class="ops-numeric mt-0.5 text-hissa-primary">{{ displayValue(detail.filing?.revision_number, 'Unknown') }}</dd>
                    </div>
                </dl>
            </section>

            <section aria-labelledby="validation-rule-title" class="border-b border-hissa-border pb-5">
                <h3 id="validation-rule-title" class="ops-section-title">Validation rule</h3>
                <dl class="mt-3 grid gap-x-4 gap-y-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="ops-meta text-hissa-secondary">Rule code</dt>
                        <dd class="ops-technical mt-0.5 font-semibold text-hissa-primary">{{ displayValue(detail.rule?.code, 'Unknown rule') }}</dd>
                    </div>
                    <div>
                        <dt class="ops-meta text-hissa-secondary">Rule version</dt>
                        <dd class="ops-numeric mt-0.5 text-hissa-primary">{{ displayValue(detail.rule?.version, 'Unknown') }}</dd>
                    </div>
                </dl>
                <p class="ops-wrap mt-3 text-sm text-hissa-secondary">{{ displayValue(detail.rule?.description, 'No rule description was provided.') }}</p>
            </section>

            <section aria-labelledby="validation-context-title" class="border-b border-hissa-border pb-5">
                <h3 id="validation-context-title" class="ops-section-title">Validation context</h3>
                <p class="ops-wrap mt-3 text-sm text-hissa-primary">{{ displayValue(detail.message, 'No validation message was provided.') }}</p>
                <dl class="mt-4 grid gap-x-4 gap-y-3 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="ops-meta text-hissa-secondary">Expected</dt>
                        <dd class="ops-technical ops-wrap mt-0.5 break-words text-hissa-primary">{{ displayValue(detail.expectedValue) }}</dd>
                    </div>
                    <div>
                        <dt class="ops-meta text-hissa-secondary">Actual</dt>
                        <dd class="ops-technical ops-wrap mt-0.5 break-words text-hissa-primary">{{ displayValue(detail.actualValue) }}</dd>
                    </div>
                    <div>
                        <dt class="ops-meta text-hissa-secondary">Tolerance</dt>
                        <dd class="ops-technical ops-wrap mt-0.5 break-words text-hissa-primary">{{ displayValue(detail.tolerance) }}</dd>
                    </div>
                </dl>
            </section>

            <section aria-labelledby="validation-facts-title">
                <div class="flex flex-wrap items-baseline justify-between gap-3">
                    <div>
                        <h3 id="validation-facts-title" class="ops-section-title">Linked input facts</h3>
                        <p class="mt-1 text-sm text-hissa-secondary">Source context used by this validation rule.</p>
                    </div>
                    <span class="ops-meta text-hissa-muted">{{ detail.inputFacts?.length ?? 0 }} linked</span>
                </div>
                <ul v-if="(detail.inputFacts?.length ?? 0) > 0" class="mt-3 divide-y divide-hissa-border border-y border-hissa-border">
                    <li v-for="fact in (detail.inputFacts ?? [])" :key="fact.normalizedFactId" class="py-3 text-sm">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <a v-if="fact.href" :href="fact.href" class="ops-technical min-w-0 truncate text-hissa-action underline underline-offset-2 outline-none hover:text-hissa-action-hover focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" :title="displayValue(fact.normalizedFactId, 'Unknown fact')">{{ displayValue(fact.normalizedFactId, 'Unknown fact') }}</a>
                            <span v-else class="ops-technical min-w-0 truncate text-hissa-secondary" :title="displayValue(fact.normalizedFactId, 'Unknown fact')">{{ displayValue(fact.normalizedFactId, 'Unknown fact') }}</span>
                            <span class="ops-numeric ops-wrap shrink-0 text-hissa-primary">{{ displayValue(fact.value, 'Unknown') }}<span v-if="fact.currency" class="ml-1 text-hissa-secondary">{{ displayValue(fact.currency) }}</span></span>
                        </div>
                        <p class="ops-wrap mt-1 font-medium text-hissa-primary">{{ displayValue(fact.canonicalConcept, 'Unknown concept') }}</p>
                        <p class="ops-meta ops-wrap mt-0.5 text-hissa-secondary">Source concept: <span class="ops-technical">{{ displayValue(fact.sourceConcept) }}</span></p>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-hissa-secondary">No input facts are linked to this result.</p>
            </section>
        </div>

        <footer v-if="detail" class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-hissa-border pt-4">
            <p class="max-w-xl ops-meta text-hissa-secondary">Manual review does not change the financial record or reprocess this filing.</p>
            <button type="button" class="min-h-10 rounded-md border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-action outline-none transition-colors hover:bg-hissa-secondary-soft focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="reviewDialogOpen = true">Mark for review</button>
        </footer>
    </OpsDialog>

    <MarkReviewDialog v-if="reviewDialogOpen && props.detail" :open="reviewDialogOpen" entity-type="validation_result" :entity-id="displayValue(props.detail.validationResultId, 'Unknown result')" :filing-id="displayValue(props.detail.filingId, 'Unknown filing')" :expected-version="displayValue(props.detail.reviewVersion, 'Unknown version')" :capability="reviewCapability" @close="reviewDialogOpen = false" @accepted="reviewDialogOpen = false" />
</template>
