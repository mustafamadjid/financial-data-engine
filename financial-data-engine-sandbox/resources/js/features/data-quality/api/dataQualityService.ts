import { appendQueryString, decodePaginatedResponse, isRecord, requestOps } from '../../ops/api/opsHttpClient';
import type { PaginatedResponse } from '../../ops/types/ops';
import type { ValidationListParams, ValidationResultItem } from '../types/dataQuality';

export async function fetchValidationResults(filingId: string, datasetVersion: string, ruleSetVersion: string, params: ValidationListParams): Promise<PaginatedResponse<ValidationResultItem>> {
    const query = new URLSearchParams({ filing_id: filingId, dataset_version: datasetVersion, rule_set_version: ruleSetVersion, page: String(params.page), per_page: String(params.perPage) });
    appendQueryString(query, 'severity', params.severity);
    appendQueryString(query, 'result', params.result);
    appendQueryString(query, 'rule_code', params.ruleCode);
    return decodePaginatedResponse(await requestOps<unknown>(`/ops/data/validation-results?${query.toString()}`), decodeValidationResult);
}

function decodeValidationResult(value: unknown): ValidationResultItem {
    if (!isRecord(value) || typeof value.validationResultId !== 'string' || typeof value.filingId !== 'string' || !isRecord(value.rule) || !Array.isArray(value.normalizedFactIds)) {
        throw new Error('A validation result row is malformed.');
    }
    return value as unknown as ValidationResultItem;
}
