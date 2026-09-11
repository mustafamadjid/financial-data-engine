import type { PageParams } from '../../ops/types/ops';

export interface DebtCoverageSummary {
    available: boolean;
    reasonCode: string | null;
    identifiedAmount: string | null;
    denominatorAmount: string | null;
    unidentifiedAmount: string | null;
    percentage: string | null;
    currency: string | null;
    ruleVersion: string | null;
}

export interface DebtRecordListItem {
    debtRecordId: string;
    filingId: string;
    borrower: string | null;
    creditor: string | null;
    facility: string | null;
    outstandingAmount: string | null;
    limitAmount: string | null;
    currency: string | null;
    rate: string | null;
    maturity: string | null;
    collateral: string | null;
    purpose: string | null;
    classification: 'ISLAMIC' | 'CONVENTIONAL' | 'MIXED' | 'UNDETERMINED';
    classificationStatus: string;
}

export interface DebtListParams extends PageParams {
    search?: string;
    creditor?: string;
    classification?: DebtRecordListItem['classification'];
    unidentified?: boolean;
}
