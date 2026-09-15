import type { PageParams } from '../../ops/types/ops';

export type ValidationResult = 'PASS' | 'FAIL' | 'REVIEW_REQUIRED' | 'SKIPPED';
export type ValidationSeverity = 'ERROR' | 'WARN' | 'INFO';

export interface ValidationResultItem {
    validationResultId: string;
    filingId: string;
    normalizedDatasetVersion: string;
    validationRuleSetVersion: string;
    rule: { code: string; version: number; description: string };
    result: ValidationResult;
    severity: ValidationSeverity;
    expectedValue: string | null;
    actualValue: string | null;
    tolerance: string | null;
    message: string | null;
    checkedAt: string;
    reviewVersion: string;
    normalizedFactIds: string[];
    inputFacts: Array<{ normalizedFactId: string; canonicalConcept: string; value: string | null; currency: string | null; sourceConcept: string; href: string }>;
    allowedActions: { markForReview: import('../../ops/types/ops').ActionCapability };
}

export interface ValidationListParams extends PageParams {
    severity?: ValidationSeverity;
    result?: ValidationResult;
    ruleCode?: string;
}

export interface ValidationSummary {
    filingId: string;
    normalizedDatasetVersion: string;
    validationRuleSetVersion: string;
    total: number;
    byResult: Record<ValidationResult, number>;
    bySeverity: Record<ValidationSeverity, number>;
    qualityStatus: 'PENDING' | 'VERIFIED' | 'REVIEW_REQUIRED' | 'FAILED';
    verifiedInvariant: boolean;
}

export interface ValidationDetail extends ValidationResultItem {
    filing: { filing_id: string; issuer_code: string; fiscal_period: string | null; revision_number: number } | null;
}
