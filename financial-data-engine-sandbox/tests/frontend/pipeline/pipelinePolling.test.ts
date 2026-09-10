import { describe, expect, it } from 'vitest';

import { getPipelinePollingInterval } from '../../../resources/js/features/pipeline/composables/usePipelineListQuery';

describe('getPipelinePollingInterval', () => {
    it('polls active stage data frequently and idle data slowly', () => {
        expect(
            getPipelinePollingInterval({
                data: [
                    {
                        stages: {
                            DOWNLOAD: { status: 'SUCCEEDED' },
                            PARSE: { status: 'RUNNING' },
                            NORMALIZE: { status: 'NOT_STARTED' },
                            VALIDATE: { status: 'NOT_STARTED' },
                            PUBLISH: { status: 'NOT_STARTED' },
                        },
                    },
                ],
            }),
        ).toBe(10_000);
        expect(getPipelinePollingInterval({ data: [] })).toBe(60_000);
    });

    it('does not schedule polling when the browser tab is hidden', () => {
        expect(getPipelinePollingInterval({ data: [] }, false)).toBe(false);
    });
});
