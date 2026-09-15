import { describe, expect, it } from 'vitest';

import { displayValue, formatDateTime, humanize } from '../../../resources/js/features/ops/utils/formatters';

describe('ops display formatters', () => {
    it('provides stable fallbacks for missing values and unknown statuses', () => {
        expect(displayValue(null)).toBe('Not recorded');
        expect(displayValue('')).toBe('Not recorded');
        expect(humanize(null)).toBe('Unknown');
        expect(humanize('NEW_BACKEND_STATUS')).toBe('New Backend Status');
    });

    it('preserves zero, negative, decimal, and large numeric values', () => {
        expect(displayValue(0)).toBe('0');
        expect(displayValue(-1234567890.125)).toBe('-1234567890.125');
        expect(displayValue('999999999999999999999999.99')).toBe('999999999999999999999999.99');
    });

    it('does not discard invalid or partial timestamp values', () => {
        expect(formatDateTime(null)).toBe('Not recorded');
        expect(formatDateTime('not-a-timestamp')).toBe('not-a-timestamp');
    });
});
