import type {
    PaginatedResponse,
    PipelineFilingListItem,
    PipelineFilingDetail,
    PipelineHistoryEntry,
    PipelineHistoryParams,
    PipelineListParams,
    PipelinePerPage,
    PipelineSummary,
    AcceptedOperation,
} from '../types/pipeline';
import { requestOps } from '../../ops/api/opsHttpClient';

export { OpsHttpError } from '../../ops/api/opsHttpClient';

export async function fetchPipelineFilings(params: PipelineListParams): Promise<PaginatedResponse<PipelineFilingListItem>> {
    const query = new URLSearchParams();
    appendString(query, 'search', params.search);
    appendString(query, 'processing_stage', params.processingStage);
    appendString(query, 'quality_status', params.qualityStatus);
    appendString(query, 'period', params.period);
    query.set('sort', params.sort);
    query.set('page', String(params.page));
    query.set('per_page', String(params.perPage));

    const response = await requestOps<unknown>(`/ops/data/pipeline-filings?${query.toString()}`);
    return decodePipelineList(response);
}

export async function fetchPipelineSummary(): Promise<PipelineSummary> {
    const response = await requestOps<unknown>('/ops/data/pipeline-summary');
    return decodePipelineSummary(response);
}

export async function retryPipelineStage(jobRunId: number, reason?: string): Promise<AcceptedOperation> {
    const response = await requestOps<unknown>(`/ops/actions/pipeline-job-runs/${jobRunId}/retry`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(reason?.trim() ? { reason: reason.trim() } : {}),
    });
    if (!isRecord(response) || !isRecord(response.data) || response.data.operation !== 'retry') {
        throw new Error('The retry response is malformed.');
    }
    return response.data as unknown as AcceptedOperation;
}

export async function reprocessPipelineFiling(filingId: string, stage: string, reason: string): Promise<AcceptedOperation> {
    const response = await requestOps<unknown>(`/ops/actions/pipeline-filings/${encodeURIComponent(filingId)}/reprocess`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ stage, reason }),
    });
    if (!isRecord(response) || !isRecord(response.data) || response.data.operation !== 'reprocess') throw new Error('The reprocess response is malformed.');
    return response.data as unknown as AcceptedOperation;
}

export async function fetchPipelineFilingDetail(filingId: string): Promise<PipelineFilingDetail> {
    const response = await requestOps<unknown>(`/ops/data/pipeline-filings/${encodeURIComponent(filingId)}`);
    return decodePipelineDetail(response);
}

export async function fetchPipelineFilingHistory(
    filingId: string,
    params: PipelineHistoryParams,
): Promise<PaginatedResponse<PipelineHistoryEntry, PipelineHistoryParams['perPage']>> {
    const query = new URLSearchParams({ page: String(params.page), per_page: String(params.perPage) });
    const response = await requestOps<unknown>(`/ops/data/pipeline-filings/${encodeURIComponent(filingId)}/history?${query.toString()}`);
    return decodePipelineHistory(response);
}

function decodePipelineList(value: unknown): PaginatedResponse<PipelineFilingListItem> {
    if (!isRecord(value) || !Array.isArray(value.data) || !isRecord(value.meta)) {
        throw new Error('The pipeline list response is malformed.');
    }

    const meta = value.meta;
    if (!isPositiveInteger(meta.currentPage) || !isPipelinePerPage(meta.perPage) || !isPositiveInteger(meta.lastPage) || !isNonNegativeInteger(meta.total)) {
        throw new Error('The pipeline list pagination metadata is malformed.');
    }

    return {
        data: value.data.map(decodePipelineFiling),
        meta: {
            currentPage: meta.currentPage,
            perPage: meta.perPage,
            lastPage: meta.lastPage,
            total: meta.total,
        },
    };
}

function decodePipelineFiling(value: unknown): PipelineFilingListItem {
    if (!isRecord(value)) {
        throw new Error('A pipeline filing row is malformed.');
    }

    const requiredStrings = ['filingId', 'issuerCode', 'reportType', 'fiscalPeriod', 'processingStage', 'qualityStatus'] as const;
    if (requiredStrings.some((key) => typeof value[key] !== 'string') || !isPositiveInteger(value.fiscalYear) || !isNonNegativeInteger(value.revisionNumber)) {
        throw new Error('A pipeline filing row is missing required fields.');
    }
    if (!isRecord(value.stages) || !isRecord(value.allowedActions)) {
        throw new Error('A pipeline filing row has malformed stage or action data.');
    }

    return value as unknown as PipelineFilingListItem;
}

function decodePipelineSummary(value: unknown): PipelineSummary {
    if (!isRecord(value) || !isRecord(value.data)) {
        throw new Error('The pipeline summary response is malformed.');
    }
    const summary = value.data;
    const keys = ['total', 'active', 'failed', 'verified', 'reviewRequired', 'pending', 'failedExecutions'] as const;
    if (keys.some((key) => !isNonNegativeInteger(summary[key]))) {
        throw new Error('The pipeline summary response is malformed.');
    }

    return summary as unknown as PipelineSummary;
}

function decodePipelineDetail(value: unknown): PipelineFilingDetail {
    if (!isRecord(value) || !isRecord(value.data)) {
        throw new Error('The pipeline detail response is malformed.');
    }
    const detail = value.data;
    const requiredStrings = ['filingId', 'issuerCode', 'reportType', 'fiscalPeriod', 'processingStage', 'qualityStatus'] as const;
    if (requiredStrings.some((key) => typeof detail[key] !== 'string') || !isPositiveInteger(detail.fiscalYear) || !isNonNegativeInteger(detail.revisionNumber) || !Array.isArray(detail.stageAttempts) || !Array.isArray(detail.artifacts)) {
        throw new Error('The pipeline detail response is malformed.');
    }
    if (detail.currentRun !== null && !isRecord(detail.currentRun)) {
        throw new Error('The pipeline detail response is malformed.');
    }

    return detail as unknown as PipelineFilingDetail;
}

function decodePipelineHistory(value: unknown): PaginatedResponse<PipelineHistoryEntry, PipelineHistoryParams['perPage']> {
    if (!isRecord(value) || !Array.isArray(value.data) || !isRecord(value.meta)) {
        throw new Error('The pipeline history response is malformed.');
    }
    const meta = value.meta;
    if (!isPositiveInteger(meta.currentPage) || !isHistoryPerPage(meta.perPage) || !isPositiveInteger(meta.lastPage) || !isNonNegativeInteger(meta.total)) {
        throw new Error('The pipeline history pagination metadata is malformed.');
    }
    if (value.data.some((entry) => !isPipelineHistoryEntry(entry))) {
        throw new Error('A pipeline history entry is malformed.');
    }

    return {
        data: value.data as PipelineHistoryEntry[],
        meta: { currentPage: meta.currentPage, perPage: meta.perPage, lastPage: meta.lastPage, total: meta.total },
    };
}

function appendString(query: URLSearchParams, key: string, value: string | undefined): void {
    const normalized = value?.trim();
    if (normalized !== undefined && normalized !== '') {
        query.set(key, normalized);
    }
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}

function isPositiveInteger(value: unknown): value is number {
    return typeof value === 'number' && Number.isInteger(value) && value >= 1;
}

function isNonNegativeInteger(value: unknown): value is number {
    return typeof value === 'number' && Number.isInteger(value) && value >= 0;
}

function isPipelinePerPage(value: unknown): value is PipelinePerPage {
    return value === 25 || value === 50 || value === 100;
}

function isHistoryPerPage(value: unknown): value is 10 | 25 | 50 {
    return value === 10 || value === 25 || value === 50;
}

function isPipelineHistoryEntry(value: unknown): value is PipelineHistoryEntry {
    return isRecord(value)
        && typeof value.id === 'string'
        && (value.type === 'pipelineRun' || value.type === 'jobAttempt' || value.type === 'auditEvent')
        && (value.actorId === null || typeof value.actorId === 'string');
}
