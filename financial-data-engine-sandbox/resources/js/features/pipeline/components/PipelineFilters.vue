<script setup lang="ts">
import SearchInput from '../../../components/ops/SearchInput.vue';
import type { PipelinePerPage, PipelineProcessingStage, PipelineSort, QualityStatus } from '../types/pipeline';

defineProps<{
    search: string;
    processingStage?: PipelineProcessingStage;
    qualityStatus?: QualityStatus;
    period?: string;
    sort: PipelineSort;
    perPage: PipelinePerPage;
}>();

const emit = defineEmits<{
    'update:search': [value: string];
    'update:processing-stage': [value: PipelineProcessingStage | undefined];
    'update:quality-status': [value: QualityStatus | undefined];
    'update:period': [value: string | undefined];
    'update:sort': [value: PipelineSort];
    'update:per-page': [value: PipelinePerPage];
}>();

function inputValue(event: Event): string {
    return (event.target as HTMLInputElement).value;
}

function optionalValue(event: Event): string | undefined {
    const value = inputValue(event).trim();
    return value === '' ? undefined : value;
}
</script>

<template>
    <div class="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(17rem,1.6fr)_repeat(5,minmax(7rem,1fr))]">
        <SearchInput
            id="pipeline-search"
            label="Search filings"
            :model-value="search"
            placeholder="Ticker or filing ID"
            input-class="w-full"
            @update:model-value="emit('update:search', $event)"
        />
        <label for="pipeline-quality-status" class="grid min-w-0 gap-1.5 text-[13px] font-semibold leading-[18px] text-hissa-secondary">
            Quality
            <select id="pipeline-quality-status" :value="qualityStatus" class="min-h-10 w-full min-w-0 rounded-md border border-hissa-border bg-hissa-surface px-3 text-sm font-normal text-hissa-primary outline-none transition-colors hover:border-hissa-border-strong focus-visible:border-hissa-action focus-visible:ring-2 focus-visible:ring-hissa-action/20" @change="emit('update:quality-status', optionalValue($event) as QualityStatus | undefined)">
                <option :value="undefined">All quality</option>
                <option value="PENDING">Pending</option>
                <option value="VERIFIED">Verified</option>
                <option value="REVIEW_REQUIRED">Review required</option>
                <option value="FAILED">Failed</option>
            </select>
        </label>
        <label for="pipeline-processing-stage" class="grid min-w-0 gap-1.5 text-[13px] font-semibold leading-[18px] text-hissa-secondary">
            Stage
            <select id="pipeline-processing-stage" :value="processingStage" class="min-h-10 w-full min-w-0 rounded-md border border-hissa-border bg-hissa-surface px-3 text-sm font-normal text-hissa-primary outline-none transition-colors hover:border-hissa-border-strong focus-visible:border-hissa-action focus-visible:ring-2 focus-visible:ring-hissa-action/20" @change="emit('update:processing-stage', optionalValue($event) as PipelineProcessingStage | undefined)">
                <option :value="undefined">All stages</option>
                <option value="DOWNLOADING">Downloading</option>
                <option value="PARSING">Parsing</option>
                <option value="NORMALIZING">Normalizing</option>
                <option value="VALIDATING">Validating</option>
                <option value="PUBLISHING">Publishing</option>
                <option value="FAILED">Failed</option>
            </select>
        </label>
        <label for="pipeline-period" class="grid min-w-0 gap-1.5 text-[13px] font-semibold leading-[18px] text-hissa-secondary">
            Period
            <input id="pipeline-period" :value="period" type="text" placeholder="FY 2025" class="min-h-10 w-full min-w-0 rounded-md border border-hissa-border bg-hissa-surface px-3 text-sm font-normal text-hissa-primary outline-none placeholder:text-hissa-muted transition-colors hover:border-hissa-border-strong focus-visible:border-hissa-action focus-visible:ring-2 focus-visible:ring-hissa-action/20" @input="emit('update:period', optionalValue($event))" />
        </label>
        <label for="pipeline-sort" class="grid min-w-0 gap-1.5 text-[13px] font-semibold leading-[18px] text-hissa-secondary">
            Sort
            <select id="pipeline-sort" :value="sort" class="min-h-10 w-full min-w-0 rounded-md border border-hissa-border bg-hissa-surface px-3 text-sm font-normal text-hissa-primary outline-none transition-colors hover:border-hissa-border-strong focus-visible:border-hissa-action focus-visible:ring-2 focus-visible:ring-hissa-action/20" @change="emit('update:sort', inputValue($event) as PipelineSort)">
                <option value="last_processed_desc">Latest processed</option>
                <option value="last_processed_asc">Oldest processed</option>
                <option value="issuer_asc">Issuer A-Z</option>
            </select>
        </label>
        <label for="pipeline-per-page" class="grid min-w-0 gap-1.5 text-[13px] font-semibold leading-[18px] text-hissa-secondary">
            Rows
            <select id="pipeline-per-page" :value="perPage" class="min-h-10 w-full min-w-0 rounded-md border border-hissa-border bg-hissa-surface px-3 text-sm font-normal text-hissa-primary outline-none transition-colors hover:border-hissa-border-strong focus-visible:border-hissa-action focus-visible:ring-2 focus-visible:ring-hissa-action/20" @change="emit('update:per-page', Number(inputValue($event)) as PipelinePerPage)">
                <option :value="25">25</option>
                <option :value="50">50</option>
                <option :value="100">100</option>
            </select>
        </label>
    </div>
</template>
