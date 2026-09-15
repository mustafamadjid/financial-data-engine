export interface ActionCapability {
    allowed: boolean;
    reasonCode: string | null;
    reason: string | null;
}

export interface PaginationMeta<TPerPage extends number = number> {
    currentPage: number;
    perPage: TPerPage;
    lastPage: number;
    total: number;
}

export interface PaginatedResponse<TItem, TPerPage extends number = number> {
    data: TItem[];
    meta: PaginationMeta<TPerPage>;
}

export interface OpsErrorPayload {
    code: string;
    message: string;
    fieldErrors?: Record<string, string[]>;
    requestId?: string;
}

export interface PageParams {
    page: number;
    perPage: 25 | 50 | 100;
}
