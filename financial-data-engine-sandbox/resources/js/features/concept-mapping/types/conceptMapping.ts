import type { ActionCapability, PageParams } from '../../ops/types/ops';

export type MappingStatus = 'DRAFT' | 'APPROVED' | 'REJECTED' | 'REVIEW_REQUIRED';
export type MappingDisplayStatus = MappingStatus | 'UNMAPPED';

export interface MappingInventoryItem {
    mappingSeriesKey: string;
    sourceConcept: string;
    entryPoint: string | null;
    currentMapping: {
        mappingRuleId: string;
        canonicalConcept: { code: string; name: string };
        status: MappingStatus;
        rationale: string | null;
        reviewer: string | null;
        version: number;
    } | null;
    displayStatus: MappingDisplayStatus;
    affectedFilingCount: number;
    allowedActions: Record<'edit' | 'viewHistory' | 'previewImpact' | 'reprocess', ActionCapability>;
}

export interface MappingListParams extends PageParams {
    search?: string;
    status?: MappingDisplayStatus;
    entryPoint?: string;
}
