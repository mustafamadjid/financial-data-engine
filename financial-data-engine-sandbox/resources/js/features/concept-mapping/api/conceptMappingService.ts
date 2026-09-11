import { appendQueryString, decodePaginatedResponse, isRecord, requestOps } from '../../ops/api/opsHttpClient';
import type { PaginatedResponse } from '../../ops/types/ops';
import type { CanonicalOption, CreateMappingVersionPayload, MappingHistoryItem, MappingHistoryResponse, MappingImpactPreview, MappingInventoryItem, MappingListParams, ReprocessAcceptedBatch, ReprocessAffectedFilingsPayload } from '../types/conceptMapping';

export async function fetchMappingInventory(params: MappingListParams): Promise<PaginatedResponse<MappingInventoryItem>> {
    const query = new URLSearchParams({ page: String(params.page), per_page: String(params.perPage) });
    appendQueryString(query, 'search', params.search);
    appendQueryString(query, 'status', params.status);
    appendQueryString(query, 'entry_point', params.entryPoint);
    return decodePaginatedResponse(await requestOps<unknown>(`/ops/data/concept-mappings?${query.toString()}`), decodeMappingInventoryItem);
}

export async function fetchMappingHistory(mappingSeriesKey: string, page = 1, perPage = 25): Promise<MappingHistoryResponse> {
    const response = await requestOps<unknown>(`/ops/data/concept-mappings/${encodeURIComponent(mappingSeriesKey)}/history?page=${page}&per_page=${perPage}`);
    if (!isRecord(response) || typeof response.mappingSeriesKey !== 'string' || !Array.isArray(response.data) || !isRecord(response.meta)) {
        throw new Error('The concept mapping history response is malformed.');
    }
    return {
        mappingSeriesKey: response.mappingSeriesKey,
        data: response.data.map(decodeMappingHistoryItem),
        meta: decodeMeta(response.meta),
    };
}

export async function fetchMappingImpact(mappingSeriesKey: string, version: number | null = null): Promise<MappingImpactPreview> {
    const query = new URLSearchParams({ per_page: '25' });
    if (version !== null) query.set('version', String(version));
    const response = await requestOps<unknown>(`/ops/data/concept-mappings/${encodeURIComponent(mappingSeriesKey)}/impact?${query.toString()}`);
    if (!isRecord(response) || !isRecord(response.data)) throw new Error('The concept mapping impact response is malformed.');
    return response.data as unknown as MappingImpactPreview;
}

export async function fetchCanonicalOptions(search = ''): Promise<CanonicalOption[]> {
    const query = new URLSearchParams();
    appendQueryString(query, 'search', search);
    const response = await requestOps<unknown>(`/ops/data/concept-mappings/canonical-options?${query.toString()}`);
    if (!isRecord(response) || !Array.isArray(response.data)) throw new Error('The canonical concept response is malformed.');
    return response.data.map((value): CanonicalOption => {
        if (!isRecord(value) || typeof value.code !== 'string' || typeof value.name !== 'string') throw new Error('A canonical concept option is malformed.');
        return { code: value.code, name: value.name };
    });
}

export async function createMappingVersion(mappingSeriesKey: string, payload: CreateMappingVersionPayload): Promise<MappingHistoryItem> {
    const response = await requestOps<unknown>('/ops/actions/concept-mappings/' + encodeURIComponent(mappingSeriesKey) + '/versions', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            source_concept: payload.sourceConcept,
            entry_point: payload.entryPoint,
            canonical_concept: payload.canonicalConcept,
            allowed_scope: payload.allowedScope,
            period_type: payload.periodType,
            sign_convention: payload.signConvention,
            status: payload.status,
            rationale: payload.rationale,
            evidence_ids: payload.evidenceIds,
            expected_version: payload.expectedVersion,
        }),
    });
    if (!isRecord(response) || !isRecord(response.data)) throw new Error('The mapping version response is malformed.');
    return decodeMappingHistoryItem(response.data);
}

export async function reprocessAffectedFilings(mappingSeriesKey: string, payload: ReprocessAffectedFilingsPayload): Promise<ReprocessAcceptedBatch> {
    const response = await requestOps<unknown>('/ops/actions/concept-mappings/' + encodeURIComponent(mappingSeriesKey) + '/reprocess', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            filing_ids: payload.filingIds,
            expected_mapping_set_version: payload.expectedMappingSetVersion,
            reason: payload.reason,
        }),
    });
    if (!isRecord(response) || !isRecord(response.data)) throw new Error('The mapping reprocess response is malformed.');
    const data = response.data;
    if (typeof data.mappingSeriesKey !== 'string' || typeof data.mappingSetVersion !== 'number' || typeof data.acceptedCount !== 'number' || !Array.isArray(data.runs)) {
        throw new Error('The mapping reprocess response is malformed.');
    }
    return {
        mappingSeriesKey: data.mappingSeriesKey,
        mappingSetVersion: data.mappingSetVersion,
        acceptedCount: data.acceptedCount,
        runs: data.runs.map((run): ReprocessAcceptedBatch['runs'][number] => {
            if (!isRecord(run) || typeof run.pipelineRunId !== 'number' || typeof run.filingId !== 'string' || typeof run.correlationId !== 'string' || run.stage !== 'NORMALIZE') {
                throw new Error('A mapping reprocess run is malformed.');
            }
            return { pipelineRunId: run.pipelineRunId, filingId: run.filingId, correlationId: run.correlationId, stage: 'NORMALIZE' };
        }),
    };
}

function decodeMappingInventoryItem(value: unknown): MappingInventoryItem {
    if (!isRecord(value) || typeof value.mappingSeriesKey !== 'string' || typeof value.sourceConcept !== 'string' || typeof value.displayStatus !== 'string') {
        throw new Error('A concept mapping row is malformed.');
    }
    return value as unknown as MappingInventoryItem;
}

function decodeMappingHistoryItem(value: unknown): MappingHistoryItem {
    if (!isRecord(value) || typeof value.mappingRuleId !== 'string' || typeof value.version !== 'number' || !isRecord(value.canonicalConcept)) throw new Error('A concept mapping history row is malformed.');
    return value as unknown as MappingHistoryItem;
}

function decodeMeta(value: Record<string, unknown>): { currentPage: number; perPage: number; lastPage: number; total: number } {
    const currentPage = value.currentPage;
    const perPage = value.perPage;
    const lastPage = value.lastPage;
    const total = value.total;
    if (!isPositiveInteger(currentPage) || !isPositiveInteger(perPage) || !isPositiveInteger(lastPage) || !isNonNegativeInteger(total)) throw new Error('The concept mapping pagination metadata is malformed.');
    return { currentPage, perPage, lastPage, total };
}

function isPositiveInteger(value: unknown): value is number {
    return typeof value === 'number' && Number.isInteger(value) && value >= 1;
}

function isNonNegativeInteger(value: unknown): value is number {
    return typeof value === 'number' && Number.isInteger(value) && value >= 0;
}
