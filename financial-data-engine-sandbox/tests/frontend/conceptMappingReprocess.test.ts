import { afterEach, describe, expect, it, vi } from 'vitest';

import { reprocessAffectedFilings } from '../../resources/js/features/concept-mapping/api/conceptMappingService';

afterEach(() => vi.unstubAllGlobals());

describe('reprocessAffectedFilings', () => {
    it('posts the explicit impact selection and decodes the accepted batch', async () => {
        const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify({
            data: {
                mappingSeriesKey: 'series-assets',
                mappingSetVersion: 2,
                acceptedCount: 1,
                runs: [{ pipelineRunId: 4, filingId: 'FIL-1', correlationId: 'corr-1', stage: 'NORMALIZE' }],
            },
        }), { status: 202 }));
        vi.stubGlobal('fetch', fetchMock);

        await expect(reprocessAffectedFilings('series-assets', {
            filingIds: ['FIL-1'], expectedMappingSetVersion: 2, reason: 'Apply reviewed mapping.',
        })).resolves.toMatchObject({ mappingSetVersion: 2, acceptedCount: 1 });

        expect(fetchMock).toHaveBeenCalledWith('/ops/actions/concept-mappings/series-assets/reprocess', expect.objectContaining({ method: 'POST' }));
    });
});
