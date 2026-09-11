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
        sourceConcept?: string;
        entryPoint?: string | null;
        allowedScope?: string[] | null;
        periodType?: string | null;
        signConvention?: string | null;
        evidenceIds?: string[];
        supersedesMappingRuleId?: string | null;
        createdBy?: string | null;
        createdAt?: string | null;
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

export interface MappingHistoryItem {
    mappingRuleId: string;
    mappingSeriesKey: string;
    sourceConcept: string;
    entryPoint: string | null;
    canonicalConcept: { code: string; name: string };
    status: MappingStatus;
    rationale: string | null;
    reviewer: string | null;
    version: number;
    evidenceIds: string[];
    supersedesMappingRuleId: string | null;
    createdBy: string | null;
    createdAt: string | null;
}

export interface MappingHistoryResponse {
    mappingSeriesKey: string;
    data: MappingHistoryItem[];
    meta: { currentPage: number; perPage: number; lastPage: number; total: number };
}

export interface MappingImpactPreview {
    mappingSeriesKey: string;
    mappingSetVersion: number;
    selectedMapping: MappingHistoryItem;
    affectedFilingCount: number;
    filings: Array<{
        filingId: string;
        issuerCode: string;
        fiscalPeriod: string | null;
        revisionNumber: number;
        periodEnd: string | null;
        processingStage: string | null;
    }>;
    meta: { currentPage: number; perPage: number; lastPage: number; total: number };
    stageChain: string[];
    upstream: {
        rawFactsReused: boolean;
        parsedArtifactsReused: boolean;
        priorNormalizedFactsPreserved: boolean;
        automaticReprocess: boolean;
    };
    allowedActions: { reprocess: ActionCapability };
}

export interface ReprocessAffectedFilingsPayload {
    filingIds: string[];
    expectedMappingSetVersion: number;
    reason: string;
}

export interface ReprocessAcceptedBatch {
    mappingSeriesKey: string;
    mappingSetVersion: number;
    acceptedCount: number;
    runs: Array<{ pipelineRunId: number; filingId: string; correlationId: string; stage: 'NORMALIZE' }>;
}

export interface CanonicalOption {
    code: string;
    name: string;
}

export interface CreateMappingVersionPayload {
    sourceConcept: string;
    entryPoint: string | null;
    canonicalConcept: string;
    allowedScope: string[] | null;
    periodType: 'INSTANT' | 'DURATION' | null;
    signConvention: string | null;
    status: MappingStatus;
    rationale: string;
    evidenceIds: string[];
    expectedVersion: number | null;
}
