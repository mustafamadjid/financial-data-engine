<script setup lang="ts">
import { computed, ref } from 'vue';

import AsyncState from '../../../components/ops/AsyncState.vue';
import OpsDialog from '../../../components/ops/OpsDialog.vue';
import StatusBadge, { type StatusBadgeTone } from '../../../components/ops/StatusBadge.vue';
import { displayValue, formatDateTime, humanize } from '../../ops/utils/formatters';
import MarkReviewDialog from '../../review-items/components/MarkReviewDialog.vue';
import type { FinancialFactDetail } from '../types/financialReview';

const props = defineProps<{
    normalizedFactId?: string;
    fact?: FinancialFactDetail;
    loading: boolean;
    error: string | null;
}>();

const emit = defineEmits<{ close: []; retry: [] }>();
const reviewDialogOpen = ref(false);
const selectedIdentifier = computed(() => props.fact?.normalizedFactId ?? props.normalizedFactId ?? 'Selected fact');
const reviewCapability = computed(() => props.fact?.allowedActions?.markForReview ?? { allowed: false, reason: 'Review capability was not returned.' });

function statusTone(status: unknown): StatusBadgeTone {
    if (status === 'NORMALIZED' || status === 'VERIFIED' || status === 'PASS') return 'success';
    if (status === 'REVIEW_REQUIRED' || status === 'PENDING') return 'warning';
    if (status === 'FAILED' || status === 'FAIL') return 'danger';
    if (status === 'UNMAPPED' || status === 'SKIPPED') return 'neutral';
    if (status === 'PENDING') return 'warning';
    return 'neutral';
}

function formatDateOnly(value: string | null | undefined): string {
    return displayValue(value);
}
</script>

<template>
    <OpsDialog v-if="!reviewDialogOpen" title-id="financial-fact-detail-title" description-id="financial-fact-detail-description" panel-class="max-w-3xl" @close="emit('close')">
        <header class="flex items-start justify-between gap-4 border-b border-hissa-border pb-4">
            <div class="min-w-0">
                <h2 id="financial-fact-detail-title" class="ops-section-title">Normalized fact</h2>
                <p id="financial-fact-detail-description" class="mt-1 text-sm text-hissa-secondary">Compare the normalized value with its source, context, mapping, and validation evidence.</p>
                <p class="ops-technical ops-wrap mt-2 text-hissa-secondary" :title="displayValue(selectedIdentifier)">{{ displayValue(selectedIdentifier) }}</p>
            </div>
            <button type="button" aria-label="Close financial fact detail" class="min-h-10 shrink-0 rounded-md border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-secondary outline-none transition-colors hover:bg-hissa-surface-subtle focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="emit('close')">Close</button>
        </header>

        <AsyncState v-if="loading" embedded state="loading" title="Loading fact lineage" message="Fetching source, context, mapping, and validation links." />
        <AsyncState v-else-if="error" embedded state="error" title="Fact lineage could not be loaded" :message="error">
            <template #action><button type="button" class="min-h-10 rounded-md bg-hissa-action px-3 py-2 text-sm font-semibold text-white outline-none transition-colors hover:bg-hissa-action-hover focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="emit('retry')">Try again</button></template>
        </AsyncState>
        <div v-else-if="fact" class="mt-5 space-y-5 pr-1">
            <section aria-labelledby="fact-value-title" class="border-b border-hissa-border pb-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 id="fact-value-title" class="ops-section-title">Value under review</h3>
                        <p class="ops-wrap max-w-[28rem] mt-1 text-sm text-hissa-secondary">{{ displayValue(fact.canonicalConcept?.name, 'Unmapped source fact') }}</p>
                    </div>
                    <div class="text-right">
                        <p class="ops-numeric ops-wrap max-w-[18rem] text-[22px] font-semibold leading-7 text-hissa-primary">{{ displayValue(fact.value, 'Unknown') }}</p>
                        <p class="ops-wrap ops-meta mt-0.5 text-hissa-secondary">{{ displayValue(fact.currency, 'Unit not recorded') }}</p>
                    </div>
                </div>
                <dl class="mt-4 grid gap-x-4 gap-y-3 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="ops-meta text-hissa-secondary">Canonical concept</dt>
                        <dd class="ops-technical ops-wrap mt-0.5 text-hissa-primary" :title="displayValue(fact.canonicalConcept?.code, 'No canonical concept')">{{ displayValue(fact.canonicalConcept?.code, 'No canonical concept') }}</dd>
                    </div>
                    <div>
                        <dt class="ops-meta text-hissa-secondary">Period</dt>
                        <dd class="ops-wrap mt-0.5 font-semibold text-hissa-primary">{{ displayValue(fact.period, 'Unknown period') }}</dd>
                    </div>
                    <div>
                        <dt class="ops-meta text-hissa-secondary">Scope / type</dt>
                        <dd class="mt-0.5 text-hissa-primary">{{ humanize(fact.scope ?? 'UNKNOWN') }} · {{ humanize(fact.dataType) }}</dd>
                    </div>
                    <div>
                        <dt class="ops-meta text-hissa-secondary">Issuer</dt>
                        <dd class="ops-wrap mt-0.5 font-semibold text-hissa-primary">{{ displayValue(fact.filing?.issuerCode, 'Unknown issuer') }}</dd>
                    </div>
                    <div>
                        <dt class="ops-meta text-hissa-secondary">Fiscal period</dt>
                        <dd class="ops-wrap mt-0.5 text-hissa-primary">{{ displayValue(fact.filing?.fiscalPeriod, 'Unknown period') }}</dd>
                    </div>
                    <div>
                        <dt class="ops-meta text-hissa-secondary">Revision</dt>
                        <dd class="ops-numeric mt-0.5 text-hissa-primary">{{ fact.filing?.revisionNumber ?? 'Unknown' }}</dd>
                    </div>
                </dl>
            </section>

            <section aria-labelledby="fact-dimensions-title" class="border-b border-hissa-border pb-5">
                <h3 id="fact-dimensions-title" class="ops-section-title">Dimensions and context</h3>
                <dl class="mt-3 grid gap-x-4 gap-y-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="ops-meta text-hissa-secondary">Context ID</dt>
                        <dd class="ops-technical ops-wrap mt-0.5 break-all text-hissa-primary">{{ displayValue(fact.rawFact?.context?.contextId) }}</dd>
                    </div>
                    <div>
                        <dt class="ops-meta text-hissa-secondary">Entity identifier</dt>
                        <dd class="ops-technical ops-wrap mt-0.5 break-words text-hissa-primary">{{ displayValue(fact.rawFact?.context?.entityIdentifier) }}</dd>
                    </div>
                    <div>
                        <dt class="ops-meta text-hissa-secondary">Period type</dt>
                        <dd class="ops-wrap mt-0.5 text-hissa-primary">{{ displayValue(fact.rawFact?.context?.periodType) }}</dd>
                    </div>
                    <div>
                        <dt class="ops-meta text-hissa-secondary">Instant date</dt>
                        <dd class="mt-0.5 text-hissa-primary">{{ formatDateOnly(fact.rawFact?.context?.instantDate) }}</dd>
                    </div>
                    <div>
                        <dt class="ops-meta text-hissa-secondary">Start / end</dt>
                        <dd class="mt-0.5 text-hissa-primary">{{ formatDateOnly(fact.periodStart) }} <span aria-hidden="true">→</span> {{ formatDateOnly(fact.periodEnd) }}</dd>
                    </div>
                    <div>
                        <dt class="ops-meta text-hissa-secondary">Unit measure</dt>
                        <dd class="ops-technical ops-wrap mt-0.5 text-hissa-primary">{{ displayValue(fact.rawFact?.unit?.measure ?? fact.currency) }}</dd>
                    </div>
                </dl>
            </section>

            <section aria-labelledby="fact-provenance-title" class="border-b border-hissa-border pb-5">
                <h3 id="fact-provenance-title" class="ops-section-title">Provenance and mapping</h3>
                <dl class="mt-3 grid gap-x-4 gap-y-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="ops-meta text-hissa-secondary">Source concept</dt>
                        <dd class="ops-technical ops-wrap mt-0.5 break-words text-hissa-primary">{{ displayValue(fact.rawFact?.sourceConcept ?? fact.sourceConcept) }}</dd>
                    </div>
                    <div>
                        <dt class="ops-meta text-hissa-secondary">Source namespace</dt>
                        <dd class="ops-technical ops-wrap mt-0.5 break-words text-hissa-primary">{{ displayValue(fact.rawFact?.sourceNamespace) }}</dd>
                    </div>
                    <div>
                        <dt class="ops-meta text-hissa-secondary">Raw fact ID</dt>
                        <dd class="ops-technical ops-wrap mt-0.5 break-all text-hissa-primary">{{ displayValue(fact.rawFact?.rawFactId) }}</dd>
                    </div>
                    <div>
                        <dt class="ops-meta text-hissa-secondary">Normalization version</dt>
                        <dd class="ops-technical ops-wrap mt-0.5 text-hissa-primary">{{ displayValue(fact.normalizationVersion) }}</dd>
                    </div>
                    <div>
                        <dt class="ops-meta text-hissa-secondary">Mapping</dt>
                        <dd class="ops-wrap mt-0.5 text-hissa-primary">{{ displayValue(fact.mapping?.canonicalConcept, 'No applicable mapping') }}</dd>
                    </div>
                    <div>
                        <dt class="ops-meta text-hissa-secondary">Mapping version / status</dt>
                        <dd class="mt-0.5 text-hissa-primary">{{ fact.mapping ? `v${fact.mapping.ruleVersion} · ${humanize(fact.mapping.status)}` : 'Not recorded' }}</dd>
                    </div>
                </dl>
                <p v-if="fact.mapping?.rationale" class="ops-wrap mt-3 text-sm text-hissa-secondary">{{ displayValue(fact.mapping.rationale) }}</p>
            </section>

            <section aria-labelledby="fact-anomalies-title">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 id="fact-anomalies-title" class="ops-section-title">Anomalies and validation</h3>
                        <p class="mt-1 text-sm text-hissa-secondary">Explicit states and checks linked to this normalized fact.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <StatusBadge :label="humanize(fact.normalizationStatus)" :tone="statusTone(fact.normalizationStatus)" />
                        <StatusBadge :label="humanize(fact.validationStatus)" :tone="statusTone(fact.validationStatus)" />
                    </div>
                </div>
                <div class="mt-3 grid gap-x-4 gap-y-3 border-y border-hissa-border py-3 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="ops-meta text-hissa-secondary">Raw value</dt>
                        <dd class="ops-numeric ops-wrap mt-0.5 break-words text-hissa-primary">{{ displayValue(fact.rawFact?.rawValue) }}</dd>
                    </div>
                    <div>
                        <dt class="ops-meta text-hissa-secondary">Normalized numeric value</dt>
                        <dd class="ops-numeric ops-wrap mt-0.5 break-words text-hissa-primary">{{ displayValue(fact.rawFact?.normalizedNumericValue) }}</dd>
                    </div>
                    <div>
                        <dt class="ops-meta text-hissa-secondary">Fact status / nil</dt>
                        <dd class="ops-wrap mt-0.5 text-hissa-primary">{{ displayValue(fact.rawFact?.factStatus) }} <span aria-hidden="true">·</span> {{ fact.rawFact?.isNil === true ? 'Nil' : fact.rawFact?.isNil === false ? 'Value present' : 'Nil state not recorded' }}</dd>
                    </div>
                </div>
                <p v-if="(fact.validationResults?.length ?? 0) === 0" class="mt-3 text-sm text-hissa-secondary">No validation results are linked to this fact.</p>
                <ul v-else class="mt-3 divide-y divide-hissa-border border-y border-hissa-border">
                    <li v-for="result in (fact.validationResults ?? [])" :key="result.validationResultId" class="py-3 text-sm">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="ops-technical ops-wrap font-semibold text-hissa-primary">{{ displayValue(result.ruleCode, 'Unknown rule') }} <span class="font-sans font-normal text-hissa-muted">· v{{ displayValue(result.ruleVersion, 'Unknown') }}</span></p>
                                <p v-if="result.message" class="ops-wrap mt-1 text-hissa-secondary">{{ displayValue(result.message) }}</p>
                            </div>
                            <div class="flex shrink-0 flex-wrap gap-1.5">
                                <StatusBadge :label="humanize(result.severity)" :tone="statusTone(result.severity)" />
                                <StatusBadge :label="humanize(result.result)" :tone="statusTone(result.result)" />
                            </div>
                        </div>
                        <dl class="mt-2 flex flex-wrap gap-x-4 gap-y-1 ops-meta text-hissa-secondary">
                            <div v-if="result.expectedValue != null"><dt class="inline font-semibold">Expected</dt> <dd class="inline ops-technical ops-wrap">{{ displayValue(result.expectedValue) }}</dd></div>
                            <div v-if="result.actualValue != null"><dt class="inline font-semibold">Actual</dt> <dd class="inline ops-technical ops-wrap">{{ displayValue(result.actualValue) }}</dd></div>
                            <div v-if="result.tolerance != null"><dt class="inline font-semibold">Tolerance</dt> <dd class="inline ops-technical ops-wrap">{{ displayValue(result.tolerance) }}</dd></div>
                            <div><dt class="inline font-semibold">Checked</dt> <dd class="inline">{{ formatDateTime(result.checkedAt) }}</dd></div>
                        </dl>
                    </li>
                </ul>
            </section>
        </div>

        <footer v-if="fact" class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-hissa-border pt-4">
            <p class="max-w-xl ops-meta text-hissa-secondary">Manual review does not change the normalized value or reprocess the filing.</p>
            <button type="button" class="min-h-10 rounded-md border border-hissa-border px-3 py-2 text-sm font-semibold text-hissa-action outline-none transition-colors hover:bg-hissa-secondary-soft focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2" @click="reviewDialogOpen = true">Mark for review</button>
        </footer>
    </OpsDialog>

    <MarkReviewDialog v-if="reviewDialogOpen && props.fact" :open="reviewDialogOpen" entity-type="normalized_fact" :entity-id="displayValue(props.fact.normalizedFactId, 'Unknown fact')" :filing-id="displayValue(props.fact.filing?.filingId, 'Unknown filing')" :expected-version="displayValue(props.fact.reviewVersion, 'Unknown version')" :capability="reviewCapability" @close="reviewDialogOpen = false" @accepted="reviewDialogOpen = false" />
</template>
