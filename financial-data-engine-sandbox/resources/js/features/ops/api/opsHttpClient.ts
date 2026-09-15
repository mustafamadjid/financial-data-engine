import type { OpsErrorPayload, PaginatedResponse } from '../types/ops';

export class OpsHttpError extends Error {
    public readonly code: string;
    public readonly fieldErrors?: Record<string, string[]>;
    public readonly requestId?: string;

    public constructor(public readonly status: number, error: OpsErrorPayload) {
        super(error.message);
        this.name = 'OpsHttpError';
        this.code = error.code;
        this.fieldErrors = error.fieldErrors;
        this.requestId = error.requestId;
    }
}

export async function requestOps<T>(url: string, init: RequestInit = {}): Promise<T> {
    const response = await fetch(url, {
        ...init,
        credentials: 'omit',
        headers: { Accept: 'application/json', ...(init.headers ?? {}) },
    });
    const body = await parseJson(response);
    if (!response.ok) throw new OpsHttpError(response.status, decodeOpsError(body));
    return body as T;
}

export function decodeOpsError(value: unknown): OpsErrorPayload {
    if (!isRecord(value)) return { code: 'REQUEST_FAILED', message: 'The Ops request could not be completed.' };
    return {
        code: typeof value.code === 'string' ? value.code : 'REQUEST_FAILED',
        message: typeof value.message === 'string' ? value.message : 'The Ops request could not be completed.',
        fieldErrors: isFieldErrors(value.fieldErrors) ? value.fieldErrors : undefined,
        requestId: typeof value.requestId === 'string' ? value.requestId : undefined,
    };
}

export function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}

export function decodePaginatedResponse<TItem>(value: unknown, decodeItem: (item: unknown) => TItem): PaginatedResponse<TItem> {
    if (!isRecord(value) || !Array.isArray(value.data) || !isRecord(value.meta)) {
        throw new Error('The Ops paginated response is malformed.');
    }
    const { currentPage, perPage, lastPage, total } = value.meta;
    if (!isPositiveInteger(currentPage) || !isPositiveInteger(perPage) || !isPositiveInteger(lastPage) || !isNonNegativeInteger(total)) {
        throw new Error('The Ops pagination metadata is malformed.');
    }
    return { data: value.data.map(decodeItem), meta: { currentPage, perPage, lastPage, total } };
}

export function appendQueryString(query: URLSearchParams, key: string, value: string | null | undefined): void {
    const normalized = value?.trim();
    if (normalized !== undefined && normalized !== '') query.set(key, normalized);
}

async function parseJson(response: Response): Promise<unknown> {
    try {
        return await response.json();
    } catch {
        return null;
    }
}

function isFieldErrors(value: unknown): value is Record<string, string[]> {
    return isRecord(value) && Object.values(value).every((messages) => Array.isArray(messages) && messages.every((message) => typeof message === 'string'));
}

function isPositiveInteger(value: unknown): value is number {
    return typeof value === 'number' && Number.isInteger(value) && value >= 1;
}

function isNonNegativeInteger(value: unknown): value is number {
    return typeof value === 'number' && Number.isInteger(value) && value >= 0;
}
