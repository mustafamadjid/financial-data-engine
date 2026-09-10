export const PIPELINE_STAGE_KEYS = ['DOWNLOAD', 'PARSE', 'NORMALIZE', 'VALIDATE', 'PUBLISH'] as const;

export type PipelineStageKey = (typeof PIPELINE_STAGE_KEYS)[number];

export type PipelineProcessingStage =
    | 'DISCOVERED'
    | 'DOWNLOADING'
    | 'DOWNLOADED'
    | 'PARSING'
    | 'PARSED'
    | 'NORMALIZING'
    | 'NORMALIZED'
    | 'VALIDATING'
    | 'VALIDATED'
    | 'PUBLISHING'
    | 'PUBLISHED'
    | 'FAILED';

export type StageStatus = 'NOT_STARTED' | 'QUEUED' | 'RUNNING' | 'SUCCEEDED' | 'FAILED';
export type QualityStatus = 'PENDING' | 'VERIFIED' | 'REVIEW_REQUIRED' | 'FAILED';
export type PipelineSort = 'last_processed_desc' | 'last_processed_asc' | 'issuer_asc';
export type PipelinePerPage = 25 | 50 | 100;

export interface PipelineStageSummary {
    stage: PipelineStageKey;
    status: StageStatus;
    attempt: number | null;
    startedAt: string | null;
    finishedAt: string | null;
}

export interface AllowedAction {
    allowed: boolean;
    reasonCode: string | null;
    reason: string | null;
}

export interface PipelineErrorSummary {
    code: string | null;
    message: string;
    stage?: PipelineStageKey;
}

export interface PipelineFilingListItem {
    filingId: string;
    issuerCode: string;
    reportType: string;
    fiscalYear: number;
    fiscalPeriod: string;
    periodStart: string | null;
    periodEnd: string | null;
    revisionNumber: number;
    processingStage: PipelineProcessingStage;
    qualityStatus: QualityStatus;
    stages: Record<PipelineStageKey, PipelineStageSummary>;
    lastProcessedAt: string | null;
    errorSummary: PipelineErrorSummary | null;
    retryJobRunId: number | null;
    allowedActions: {
        viewDetail: AllowedAction;
        viewArtifact: AllowedAction;
        viewHistory: AllowedAction;
        retry: AllowedAction;
        reprocess: AllowedAction;
    };
}

export interface PipelineListParams {
    search?: string;
    processingStage?: PipelineProcessingStage;
    qualityStatus?: QualityStatus;
    period?: string;
    sort: PipelineSort;
    page: number;
    perPage: PipelinePerPage;
}

export interface PaginatedResponse<T, TPerPage extends number = PipelinePerPage> {
    data: T[];
    meta: {
        currentPage: number;
        perPage: TPerPage;
        lastPage: number;
        total: number;
    };
}

export interface PipelineSummary {
    total: number;
    active: number;
    failed: number;
    verified: number;
    reviewRequired: number;
    pending: number;
    failedExecutions: number;
}

export interface PipelineFilingArtifact {
    artifactId: string;
    artifactType: string;
    filename: string;
    contentType: string;
    sizeBytes: number;
    downloadedAt: string | null;
}

export interface PipelineJobAttemptDetail {
    jobRunId: number;
    stage: string;
    attempt: number;
    status: StageStatus;
    correlationId: string;
    error: PipelineErrorSummary | null;
    startedAt: string | null;
    finishedAt: string | null;
}

export interface PipelineRunDetail {
    pipelineRunId: number;
    trigger: string;
    status: string;
    startedFromStage: string | null;
    correlationId: string;
    dependencyVersions: Record<string, string>;
    startedAt: string | null;
    finishedAt: string | null;
}

export interface PipelineFilingDetail {
    filingId: string;
    issuerCode: string;
    reportType: string;
    fiscalYear: number;
    fiscalPeriod: string;
    periodStart: string | null;
    periodEnd: string | null;
    revisionNumber: number;
    processingStage: PipelineProcessingStage;
    qualityStatus: QualityStatus;
    currentRun: PipelineRunDetail | null;
    stageAttempts: PipelineJobAttemptDetail[];
    latestError: PipelineErrorSummary | null;
    artifacts: PipelineFilingArtifact[];
}

export type PipelineHistoryEntryType = 'pipelineRun' | 'jobAttempt' | 'auditEvent';

export interface PipelineHistoryEntry {
    id: string;
    type: PipelineHistoryEntryType;
    occurredAt: string | null;
    correlationId: string | null;
    status: string | null;
    action: string | null;
    actorId: string | null;
    rationale: string | null;
    stage: string | null;
    attempt: number | null;
    error: PipelineErrorSummary | null;
}

export interface PipelineHistoryParams {
    page: number;
    perPage: 10 | 25 | 50;
}

export interface OpsError {
    code: string;
    message: string;
    fieldErrors?: Record<string, string[]>;
    requestId?: string;
}

export interface AcceptedOperation {
    operation: 'retry' | 'reprocess';
    filingId: string;
    pipelineRunId: number;
    correlationId: string;
    stage: PipelineStageKey;
    attempt?: number;
    jobRunId?: number;
}
