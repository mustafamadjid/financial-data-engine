import { appendQueryString, decodePaginatedResponse, isRecord, requestOps } from '../../ops/api/opsHttpClient';
import type { PaginatedResponse } from '../../ops/types/ops';
import type { MappingInventoryItem, MappingListParams } from '../types/conceptMapping';

export async function fetchMappingInventory(params: MappingListParams): Promise<PaginatedResponse<MappingInventoryItem>> {
    const query = new URLSearchParams({ page: String(params.page), per_page: String(params.perPage) });
    appendQueryString(query, 'search', params.search);
    appendQueryString(query, 'status', params.status);
    appendQueryString(query, 'entry_point', params.entryPoint);
    return decodePaginatedResponse(await requestOps<unknown>(`/ops/data/concept-mappings?${query.toString()}`), decodeMappingInventoryItem);
}

function decodeMappingInventoryItem(value: unknown): MappingInventoryItem {
    if (!isRecord(value) || typeof value.mappingSeriesKey !== 'string' || typeof value.sourceConcept !== 'string' || typeof value.displayStatus !== 'string') {
        throw new Error('A concept mapping row is malformed.');
    }
    return value as unknown as MappingInventoryItem;
}
