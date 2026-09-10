import { describe, expect, it } from 'vitest';

import { createQueryClient } from '../../resources/js/bootstrap/queryClient';

describe('QueryClient foundation', () => {
    it('uses bounded reads and never retries mutations automatically', () => {
        const client = createQueryClient();
        const defaults = client.getDefaultOptions();

        expect(defaults.queries?.retry).toBe(2);
        expect(defaults.queries?.refetchOnWindowFocus).toBe(false);
        expect(defaults.mutations?.retry).toBe(false);
    });
});
