import type { ActionCapability, PageParams } from '../../ops/types/ops';

export type FinancialScope = 'CONSOLIDATED' | 'PARENT' | 'UNKNOWN';
export type NormalizationStatus = 'NORMALIZED' | 'UNMAPPED' | 'REVIEW_REQUIRED' | 'FAILED';
export type FinancialValidationStatus = 'PENDING' | 'VERIFIED' | 'REVIEW_REQUIRED' | 'FAILED';

export interface FinancialFactListItem {
    normalizedFactId: string;
    filing: { filingId: string; issuerCode: string; fiscalPeriod: string; revisionNumber: number };
    canonicalConcept: { code: string; name: string } | null;
    value: string | null;
    currency: string | null;
    period: string | null;
    scope: FinancialScope | null;
    dataType: 'REPORTED' | 'DERIVED';
    sourceConcept: string;
    normalizationStatus: NormalizationStatus;
    validationStatus: FinancialValidationStatus;
    allowedActions: Record<'openEvidence' | 'openMapping' | 'markForReview', ActionCapability>;
}

export interface FinancialFactListParams extends PageParams {
    filingId?: string;
    search?: string;
    scope?: FinancialScope;
    normalizationStatus?: NormalizationStatus;
    validationStatus?: FinancialValidationStatus;
    sort?: string;
}

export interface FinancialFactDetail extends FinancialFactListItem {
    periodStart: string | null;
    periodEnd: string | null;
    normalizationVersion: string | null;
    mapping: {
        mappingRuleId: string;
        mappingSeriesKey: string;
        entryPoint: string | null;
        canonicalConcept: string;
        status: string;
        ruleVersion: number;
        rationale: string | null;
    } | null;
    rawFact: {
        rawFactId: string;
        sourceConcept: string;
        sourceNamespace: string | null;
        rawValue: string | null;
        normalizedNumericValue: string | null;
        isNil: boolean;
        factStatus: string;
        context: { contextId: string; entityIdentifier: string | null; scope: string | null; periodType: string | null; instantDate: string | null; startDate: string | null; endDate: string | null } | null;
        unit: { unitId: string; unitType: string | null; measure: string | null; currency: string | null } | null;
    } | null;
    validationResults: Array<{ validationResultId: string; ruleCode: string; ruleVersion: number; result: string; severity: string; message: string | null; expectedValue: string | null; actualValue: string | null; tolerance: string | null; checkedAt: string | null }>;
}
