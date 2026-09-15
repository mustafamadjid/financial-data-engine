import { appendQueryString, decodePaginatedResponse, isRecord, requestOps } from '../../ops/api/opsHttpClient';
import type { PaginatedResponse } from '../../ops/types/ops';
import type { ValidationDetail, ValidationListParams, ValidationResultItem, ValidationSummary } from '../types/dataQuality';

export async function fetchValidationResults(filingId: string, datasetVersion: string, ruleSetVersion: string, params: ValidationListParams): Promise<PaginatedResponse<ValidationResultItem>> {
    const query = new URLSearchParams({ filing_id: filingId, dataset_version: datasetVersion, rule_set_version: ruleSetVersion, page: String(params.page), per_page: String(params.perPage) });
    appendQueryString(query, 'severity', params.severity);
    appendQueryString(query, 'result', params.result);
    appendQueryString(query, 'rule_code', params.ruleCode);
    return decodePaginatedResponse(await requestOps<unknown>(`/ops/data/validation-results?${query.toString()}`), decodeValidationResult);
}

export async function fetchValidationSummary(filingId: string, datasetVersion: string, ruleSetVersion: string): Promise<ValidationSummary> {
    const query = new URLSearchParams({ filing_id: filingId, dataset_version: datasetVersion, rule_set_version: ruleSetVersion });
    const response = await requestOps<unknown>(`/ops/data/validation-summary?${query.toString()}`);
    if (!isRecord(response) || !isRecord(response.data)) throw new Error('The validation summary response is malformed.');
    return response.data as unknown as ValidationSummary;
}

export async function fetchValidationDetail(validationResultId: string, filingId: string, datasetVersion: string, ruleSetVersion: string): Promise<ValidationDetail> {
    const query = new URLSearchParams({ filing_id: filingId, dataset_version: datasetVersion, rule_set_version: ruleSetVersion });
    const response = await requestOps<unknown>(`/ops/data/validation-results/${encodeURIComponent(validationResultId)}?${query.toString()}`);
    if (!isRecord(response) || !isRecord(response.data)) throw new Error('The validation detail response is malformed.');
    return response.data as unknown as ValidationDetail;
}

function decodeValidationResult(value: unknown): ValidationResultItem {
    if (!isRecord(value) || typeof value.validationResultId !== 'string' || typeof value.filingId !== 'string' || !isRecord(value.rule) || !Array.isArray(value.normalizedFactIds)) {
        throw new Error('A validation result row is malformed.');
    }
    return value as unknown as ValidationResultItem;
}
