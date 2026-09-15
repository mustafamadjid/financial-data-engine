<script setup lang="ts">
import DataTable from '../../../components/ops/DataTable.vue';
import StatusBadge, { type StatusBadgeTone } from '../../../components/ops/StatusBadge.vue';
import { displayValue, formatDateTime, humanize } from '../../ops/utils/formatters';
import PipelineStageCell from './PipelineStageCell.vue';
import { PIPELINE_STAGE_KEYS, type PipelineFilingListItem, type PipelineProcessingStage, type QualityStatus } from '../types/pipeline';

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

const qualityTones: Record<QualityStatus, StatusBadgeTone> = {
    PENDING: 'info',
    VERIFIED: 'success',
    REVIEW_REQUIRED: 'warning',
    FAILED: 'danger',
};

const processingTones: Record<PipelineProcessingStage, StatusBadgeTone> = {
    DISCOVERED: 'neutral',
    DOWNLOADING: 'info',
    DOWNLOADED: 'info',
    PARSING: 'info',
    PARSED: 'info',
    NORMALIZING: 'info',
    NORMALIZED: 'info',
    VALIDATING: 'warning',
    VALIDATED: 'warning',
    PUBLISHING: 'warning',
    PUBLISHED: 'success',
    FAILED: 'danger',
};

function processingTone(value: unknown): StatusBadgeTone {
    return typeof value === 'string' ? processingTones[value as PipelineProcessingStage] ?? 'neutral' : 'neutral';
}

function qualityTone(value: unknown): StatusBadgeTone {
    return typeof value === 'string' ? qualityTones[value as QualityStatus] ?? 'neutral' : 'neutral';
}
</script>

<template>
    <DataTable min-width-class="min-w-[1280px]">
        <template #caption>Filing pipeline processing status</template>
        <thead class="sticky top-0 z-10">
            <tr>
                <th scope="col" class="sticky left-0 z-20 w-56 whitespace-nowrap border-r border-hissa-border bg-hissa-surface-subtle px-3 py-2.5">Filing / revision</th>
                <th scope="col" class="w-32 whitespace-nowrap px-3 py-2.5">Period</th>
                <th scope="col" class="w-32 whitespace-nowrap px-3 py-2.5">Current stage</th>
                <th v-for="stage in stages" :key="stage" scope="col" class="w-28 whitespace-nowrap px-2 py-2.5 text-center">{{ stage }}</th>
                <th scope="col" class="w-52 whitespace-nowrap px-3 py-2.5">Quality</th>
                <th scope="col" class="w-44 whitespace-nowrap px-3 py-2.5 text-right"><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody v-if="rows.length > 0" class="divide-y divide-hissa-border">
            <tr v-for="row in rows" :key="row.filingId" :data-filing-id="row.filingId" :data-row-state="row.errorSummary != null ? 'error' : 'normal'" class="group bg-hissa-surface align-top hover:bg-hissa-surface-subtle">
                <td class="sticky left-0 z-[1] border-l border-r border-hissa-border px-3 py-3" :class="row.errorSummary != null ? 'border-l-hissa-danger bg-hissa-danger-soft/20 group-hover:bg-hissa-danger-soft/40' : 'border-l-transparent bg-hissa-surface group-hover:bg-hissa-surface-subtle'">
                    <p class="ops-wrap max-w-[15rem] font-semibold text-hissa-primary">{{ displayValue(row.issuerCode, 'Unknown issuer') }}</p>
                    <p class="ops-wrap mt-0.5 max-w-[15rem] text-xs text-hissa-secondary">{{ displayValue(row.reportType, 'Report type unknown') }} <span aria-hidden="true">·</span> Revision {{ displayValue(row.revisionNumber, 'Unknown') }}</p>
                    <p class="ops-technical mt-1 max-w-[15rem] truncate text-hissa-secondary" :title="displayValue(row.filingId, 'Unknown filing')">{{ displayValue(row.filingId, 'Unknown filing') }}</p>
                </td>
                <td class="px-3 py-3 text-hissa-secondary">
                    <span class="ops-wrap block max-w-[10rem] font-medium text-hissa-primary">{{ displayValue(row.fiscalPeriod, 'Unknown period') }}</span>
                    <span class="block ops-meta text-hissa-secondary">{{ displayValue(row.fiscalYear, 'Unknown year') }}</span>
                    <time v-if="row.lastProcessedAt" class="mt-1 block max-w-[10rem] truncate whitespace-nowrap ops-meta text-hissa-muted" :datetime="row.lastProcessedAt" :title="formatDateTime(row.lastProcessedAt)">Processed {{ formatDateTime(row.lastProcessedAt) }}</time>
                </td>
                <td class="px-3 py-3">
                    <StatusBadge :label="humanize(row.processingStage)" :tone="processingTone(row.processingStage)" />
                </td>
                <td v-for="stage in stages" :key="stage" class="px-2 py-3 text-center">
                    <PipelineStageCell :summary="row.stages?.[stage]" />
                </td>
                <td class="px-3 py-3">
                    <StatusBadge :label="humanize(row.qualityStatus)" :tone="qualityTone(row.qualityStatus)" />
                    <p v-if="row.errorSummary != null" class="ops-wrap mt-1.5 max-w-52 text-xs leading-[18px] text-hissa-danger">{{ displayValue(row.errorSummary.message, 'No error message recorded.') }}</p>
                </td>
                <td class="px-3 py-3 text-right">
                    <div class="flex min-w-0 flex-col items-end gap-1">
                        <div class="flex items-center justify-end gap-1 whitespace-nowrap">
                        <button
                            type="button"
                            class="min-h-10 rounded-md px-2.5 py-2 text-xs font-semibold text-hissa-action outline-none transition-colors hover:bg-hissa-secondary-soft focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 disabled:text-hissa-secondary"
                            :disabled="row.allowedActions?.viewDetail?.allowed !== true"
                            :title="row.allowedActions?.viewDetail?.reason ?? undefined"
                            :aria-label="`View detail for ${displayValue(row.issuerCode, 'unknown issuer')} ${displayValue(row.filingId, 'unknown filing')}`"
                            @click="emit('view-detail', row.filingId)"
                        >
                            Details
                        </button>
                        <button
                            v-if="row.allowedActions?.retry?.allowed === true && row.retryJobRunId != null"
                            type="button"
                            class="min-h-10 rounded-md px-2.5 py-2 text-xs font-semibold text-hissa-danger outline-none transition-colors hover:bg-hissa-danger-soft focus-visible:ring-2 focus-visible:ring-hissa-danger focus-visible:ring-offset-2"
                            :aria-label="`Retry failed stage for ${displayValue(row.issuerCode, 'unknown issuer')} ${displayValue(row.filingId, 'unknown filing')}`"
                            @click="emit('retry', row.filingId, row.retryJobRunId, row.errorSummary?.stage ?? 'UNKNOWN')"
                        >
                            Retry
                        </button>
                        <button
                            type="button"
                            class="min-h-10 rounded-md px-2.5 py-2 text-xs font-semibold text-hissa-secondary outline-none transition-colors hover:bg-hissa-surface-muted focus-visible:ring-2 focus-visible:ring-hissa-action focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 disabled:text-hissa-secondary"
                            :disabled="row.allowedActions?.viewHistory?.allowed !== true"
                            :title="row.allowedActions?.viewHistory?.reason ?? undefined"
                            :aria-label="`View history for ${displayValue(row.issuerCode, 'unknown issuer')} ${displayValue(row.filingId, 'unknown filing')}`"
                            @click="emit('view-history', row.filingId)"
                        >
                            History
                        </button>
                        </div>
                        <p v-if="row.allowedActions?.viewDetail?.allowed !== true && row.allowedActions?.viewDetail?.reason" class="ops-wrap max-w-[18rem] text-right ops-meta text-hissa-muted">Details unavailable: {{ row.allowedActions.viewDetail.reason }}</p>
                        <p v-if="row.allowedActions?.viewHistory?.allowed !== true && row.allowedActions?.viewHistory?.reason" class="ops-wrap max-w-[18rem] text-right ops-meta text-hissa-muted">History unavailable: {{ row.allowedActions.viewHistory.reason }}</p>
                    </div>
                </td>
            </tr>
        </tbody>
        <tbody v-else>
            <tr>
                <td :colspan="stages.length + 5" class="px-6 py-10 text-center">
                    <p class="font-semibold text-hissa-primary">{{ hasActiveFilters ? 'No filings match the current filters.' : 'No filings have been processed yet.' }}</p>
                    <p class="mt-1 text-sm text-hissa-secondary">{{ hasActiveFilters ? 'Adjust the search or filters, then try again.' : 'Pipeline records will appear here once processing begins.' }}</p>
                </td>
            </tr>
        </tbody>
    </DataTable>
</template>
