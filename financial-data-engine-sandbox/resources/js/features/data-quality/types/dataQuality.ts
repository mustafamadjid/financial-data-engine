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
    normalizedFactIds: string[];
}

export interface ValidationListParams extends PageParams {
    severity?: ValidationSeverity;
    result?: ValidationResult;
    ruleCode?: string;
}
