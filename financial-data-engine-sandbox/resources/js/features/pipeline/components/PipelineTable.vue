<script setup lang="ts">
import PipelineStageCell from './PipelineStageCell.vue';
import { PIPELINE_STAGE_KEYS, type PipelineFilingListItem } from '../types/pipeline';

defineProps<{
    rows: readonly PipelineFilingListItem[];
    hasActiveFilters: boolean;
}>();

const emit = defineEmits<{
    'view-detail': [filingId: string];
    'view-history': [filingId: string];
    retry: [filingId: string, jobRunId: number, stage: string];
}>();

const stages = PIPELINE_STAGE_KEYS;
</script>

<template>
    <div class="overflow-x-auto">
        <table class="min-w-[1080px] w-full border-separate border-spacing-0 text-left text-sm">
            <caption class="sr-only">Filing pipeline processing status</caption>
            <thead class="bg-hissa-subtle text-xs font-semibold uppercase tracking-wide text-hissa-secondary">
                <tr>
                    <th scope="col" class="w-40 border-b border-hissa-border px-3 py-3">Filing / revision</th>
                    <th scope="col" class="w-[85px] border-b border-hissa-border px-3 py-3">Period</th>
                    <th v-for="stage in stages" :key="stage" scope="col" class="border-b border-hissa-border px-3 py-3">{{ stage }}</th>
                    <th scope="col" class="w-[155px] border-b border-hissa-border px-3 py-3">Quality</th>
                    <th scope="col" class="border-b border-hissa-border px-3 py-3"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody v-if="rows.length > 0" class="divide-y divide-hissa-border">
                <tr v-for="row in rows" :key="row.filingId" :data-filing-id="row.filingId" class="bg-hissa-surface align-top hover:bg-hissa-subtle">
                    <td class="px-3 py-3">
                        <p class="font-semibold text-hissa-primary">{{ row.issuerCode }}</p>
                        <p class="mt-0.5 text-xs text-hissa-secondary">{{ row.filingId }} · Rev {{ row.revisionNumber }}</p>
                    </td>
                    <td class="px-3 py-3 text-hissa-secondary">{{ row.fiscalPeriod }} {{ row.fiscalYear }}</td>
                    <td v-for="stage in stages" :key="stage" class="px-3 py-3">
                        <PipelineStageCell :summary="row.stages[stage]" />
                    </td>
                    <td class="px-3 py-3">
                        <span class="font-medium text-hissa-primary">{{ row.qualityStatus.replace('_', ' ') }}</span>
                        <p v-if="row.errorSummary !== null" class="mt-1 max-w-44 text-xs leading-[18px] text-hissa-danger">{{ row.errorSummary.message }}</p>
                    </td>
                    <td class="px-3 py-3 text-right">
                        <div class="flex justify-end gap-2">
                            <button
                                type="button"
                                class="rounded-lg px-2 py-1 text-xs font-semibold text-hissa-action outline-none hover:bg-hissa-secondary-soft focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:text-hissa-secondary"
                                :disabled="!row.allowedActions.viewDetail.allowed"
                                :title="row.allowedActions.viewDetail.reason ?? undefined"
                                :aria-label="`View detail for ${row.issuerCode} ${row.filingId}`"
                                @click="emit('view-detail', row.filingId)"
                            >
                                Details
                            </button>
                            <button
                                v-if="row.allowedActions.retry.allowed && row.retryJobRunId !== null"
                                type="button"
                                class="rounded-lg px-2 py-1 text-xs font-semibold text-hissa-danger outline-none hover:bg-red-50 focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2"
                                :aria-label="`Retry failed stage for ${row.issuerCode} ${row.filingId}`"
                                @click="emit('retry', row.filingId, row.retryJobRunId, row.errorSummary?.stage ?? 'UNKNOWN')"
                            >
                                Retry
                            </button>
                            <button
                                type="button"
                                class="rounded-lg px-2 py-1 text-xs font-semibold text-hissa-secondary outline-none hover:bg-hissa-subtle focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:text-hissa-secondary"
                                :disabled="!row.allowedActions.viewHistory.allowed"
                                :title="row.allowedActions.viewHistory.reason ?? undefined"
                                :aria-label="`View history for ${row.issuerCode} ${row.filingId}`"
                                @click="emit('view-history', row.filingId)"
                            >
                                History
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
            <tbody v-else>
                <tr>
                    <td :colspan="stages.length + 4" class="px-6 py-12 text-center">
                        <p class="font-semibold text-hissa-primary">{{ hasActiveFilters ? 'No filings match the current filters.' : 'No filings have been processed yet.' }}</p>
                        <p class="mt-1 text-sm text-hissa-secondary">{{ hasActiveFilters ? 'Adjust the search or filters, then try again.' : 'Pipeline records will appear here once processing begins.' }}</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
