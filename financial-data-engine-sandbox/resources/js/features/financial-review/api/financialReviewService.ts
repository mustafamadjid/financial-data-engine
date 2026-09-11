import { appendQueryString, decodePaginatedResponse, isRecord, requestOps } from '../../ops/api/opsHttpClient';
import type { PaginatedResponse } from '../../ops/types/ops';
import type { FinancialFactDetail, FinancialFactListItem, FinancialFactListParams } from '../types/financialReview';

export async function fetchFinancialFacts(filingId: string | undefined, params: FinancialFactListParams): Promise<PaginatedResponse<FinancialFactListItem>> {
    const query = new URLSearchParams({ page: String(params.page), per_page: String(params.perPage) });
    appendQueryString(query, 'filing_id', filingId);
    appendQueryString(query, 'search', params.search);
    appendQueryString(query, 'scope', params.scope);
    appendQueryString(query, 'normalization_status', params.normalizationStatus);
    appendQueryString(query, 'validation_status', params.validationStatus);
    appendQueryString(query, 'sort', params.sort);
    return decodePaginatedResponse(await requestOps<unknown>(`/ops/data/financial-facts?${query.toString()}`), decodeFinancialFact);
}

export async function fetchFinancialFactDetail(normalizedFactId: string): Promise<FinancialFactDetail> {
    const response = await requestOps<unknown>(`/ops/data/financial-facts/${encodeURIComponent(normalizedFactId)}`);
    if (!isRecord(response) || !isRecord(response.data)) throw new Error('The financial fact detail response is malformed.');
    return response.data as unknown as FinancialFactDetail;
}

function decodeFinancialFact(value: unknown): FinancialFactListItem {
    if (!isRecord(value) || typeof value.normalizedFactId !== 'string' || (value.filing !== null && !isRecord(value.filing)) || (value.canonicalConcept !== null && !isRecord(value.canonicalConcept)) || typeof value.sourceConcept !== 'string') {
        throw new Error('A financial fact row is malformed.');
    }
    return value as unknown as FinancialFactListItem;
}
