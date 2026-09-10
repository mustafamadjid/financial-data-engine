<script setup lang="ts">
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
    <section aria-label="Pipeline filters" class="flex flex-wrap items-end gap-3">
        <label class="grid gap-1.5 text-[13px] font-semibold text-hissa-secondary">
            Search
            <input
                :value="search"
                type="search"
                placeholder="Search ticker or filing…"
                class="h-10 w-[280px] rounded-lg border border-hissa-border bg-hissa-surface px-3 text-sm font-normal text-hissa-primary outline-none placeholder:text-hissa-secondary focus:border-hissa-action focus:ring-2 focus:ring-hissa-action/20"
                @input="emit('update:search', inputValue($event))"
            />
        </label>
        <label class="grid gap-1.5 text-[13px] font-semibold text-hissa-secondary">
            Quality
            <select :value="qualityStatus" class="h-10 rounded-lg border border-hissa-border bg-hissa-surface px-3 text-sm font-normal text-hissa-primary outline-none focus:border-hissa-action focus:ring-2 focus:ring-hissa-action/20" @change="emit('update:quality-status', optionalValue($event) as QualityStatus | undefined)">
                <option :value="undefined">All quality</option>
                <option value="PENDING">Pending</option>
                <option value="VERIFIED">Verified</option>
                <option value="REVIEW_REQUIRED">Review required</option>
                <option value="FAILED">Failed</option>
            </select>
        </label>
        <label class="grid gap-1.5 text-[13px] font-semibold text-hissa-secondary">
            Stage
            <select :value="processingStage" class="h-10 rounded-lg border border-hissa-border bg-hissa-surface px-3 text-sm font-normal text-hissa-primary outline-none focus:border-hissa-action focus:ring-2 focus:ring-hissa-action/20" @change="emit('update:processing-stage', optionalValue($event) as PipelineProcessingStage | undefined)">
                <option :value="undefined">All stages</option>
                <option value="DOWNLOADING">Downloading</option>
                <option value="PARSING">Parsing</option>
                <option value="NORMALIZING">Normalizing</option>
                <option value="VALIDATING">Validating</option>
                <option value="PUBLISHING">Publishing</option>
                <option value="FAILED">Failed</option>
            </select>
        </label>
        <label class="grid gap-1.5 text-[13px] font-semibold text-hissa-secondary">
            Period
            <input :value="period" type="text" placeholder="FY 2025" class="h-10 w-28 rounded-lg border border-hissa-border bg-hissa-surface px-3 text-sm font-normal text-hissa-primary outline-none placeholder:text-hissa-secondary focus:border-hissa-action focus:ring-2 focus:ring-hissa-action/20" @input="emit('update:period', optionalValue($event))" />
        </label>
        <label class="grid gap-1.5 text-[13px] font-semibold text-hissa-secondary">
            Sort
            <select :value="sort" class="h-10 rounded-lg border border-hissa-border bg-hissa-surface px-3 text-sm font-normal text-hissa-primary outline-none focus:border-hissa-action focus:ring-2 focus:ring-hissa-action/20" @change="emit('update:sort', inputValue($event) as PipelineSort)">
                <option value="last_processed_desc">Latest processed</option>
                <option value="last_processed_asc">Oldest processed</option>
                <option value="issuer_asc">Issuer A–Z</option>
            </select>
        </label>
        <label class="grid gap-1.5 text-[13px] font-semibold text-hissa-secondary">
            Rows
            <select :value="perPage" class="h-10 rounded-lg border border-hissa-border bg-hissa-surface px-3 text-sm font-normal text-hissa-primary outline-none focus:border-hissa-action focus:ring-2 focus:ring-hissa-action/20" @change="emit('update:per-page', Number(inputValue($event)) as PipelinePerPage)">
                <option :value="25">25</option>
                <option :value="50">50</option>
                <option :value="100">100</option>
            </select>
        </label>
    </section>
</template>
