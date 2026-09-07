<?php

namespace App\Services\Pipeline;

use App\Models\Filing;
use App\Models\PublishedSnapshot;

final class PublishedSnapshotPersistence
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function persist(Filing $filing, array $payload, string $idempotencyKey, int $pipelineRunId): PublishedSnapshot
    {
        $lineage = array_merge((array) ($payload['lineage'] ?? []), ['pipeline_run_id' => $pipelineRunId]);
        $snapshotId = 'PUB-'.substr(hash('sha256', implode('|', [
            $filing->filing_id,
            (string) $filing->revision_number,
            (string) $pipelineRunId,
            (string) ($payload['contract_version'] ?? ''),
        ])), 0, 60);

        return PublishedSnapshot::query()->firstOrCreate(
            ['publish_idempotency_key' => $idempotencyKey],
            [
                'snapshot_id' => $snapshotId,
                'filing_id' => $filing->filing_id,
                'revision_number' => (int) $filing->revision_number,
                'publish_contract_version' => (string) ($payload['contract_version'] ?? ''),
                'normalized_dataset_version' => (string) data_get($payload, 'lineage.normalized_dataset_version'),
                'validation_rule_set_version' => (string) data_get($payload, 'quality.validation_rule_set_version'),
                'payload' => $payload,
                'lineage' => $lineage,
                'published_at' => now(),
            ],
        );
    }
}
