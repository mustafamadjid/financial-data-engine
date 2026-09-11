import { appendQueryString, decodePaginatedResponse, isRecord, requestOps } from '../../ops/api/opsHttpClient';
import type { PaginatedResponse } from '../../ops/types/ops';
import type { DebtListParams, DebtRecordListItem } from '../types/debtReview';

export async function fetchDebtRecords(filingId: string, params: DebtListParams): Promise<PaginatedResponse<DebtRecordListItem>> {
    const query = new URLSearchParams({ filing_id: filingId, page: String(params.page), per_page: String(params.perPage) });
    appendQueryString(query, 'search', params.search);
    appendQueryString(query, 'creditor', params.creditor);
    appendQueryString(query, 'classification', params.classification);
    if (params.unidentified !== undefined) query.set('unidentified', params.unidentified ? '1' : '0');
    return decodePaginatedResponse(await requestOps<unknown>(`/ops/data/debt-records?${query.toString()}`), decodeDebtRecord);
}

function decodeDebtRecord(value: unknown): DebtRecordListItem {
    if (!isRecord(value) || typeof value.debtRecordId !== 'string' || typeof value.filingId !== 'string' || typeof value.classification !== 'string') {
        throw new Error('A debt record row is malformed.');
    }
    return value as unknown as DebtRecordListItem;
}
