import { afterEach, describe, expect, it, vi } from 'vitest';

import { OpsHttpError, requestOps } from '../../../resources/js/features/ops/api/opsHttpClient';

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('requestOps', () => {
    it('decodes stable field errors and request identity', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify({
            code: 'RATIONALE_REQUIRED',
            message: 'A rationale is required.',
            fieldErrors: { rationale: ['Enter a rationale.'] },
            requestId: 'request-42',
        }), { status: 422 })));

        await expect(requestOps('/ops/actions/review-items', { method: 'POST' })).rejects.toMatchObject<Partial<OpsHttpError>>({
            status: 422,
            code: 'RATIONALE_REQUIRED',
            fieldErrors: { rationale: ['Enter a rationale.'] },
            requestId: 'request-42',
        });
    });

    it('uses a safe typed fallback when a failed response is not JSON', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('gateway unavailable', { status: 503 })));

        await expect(requestOps('/ops/data/financial-facts')).rejects.toMatchObject<Partial<OpsHttpError>>({
            status: 503,
            code: 'REQUEST_FAILED',
            message: 'The Ops request could not be completed.',
        });
    });
});
