<?php

namespace App\Application\Ops\Review;

use App\Models\AuditLog;
use App\Models\NormalizedFact;
use App\Models\ReviewItem;
use App\Models\ValidationResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MarkReviewItem
{
    /**
     * @param array{
     *     entity_type: string,
     *     entity_id: string,
     *     filing_id: string,
     *     expected_version: string,
     *     rationale: string,
     *     idempotency_key: string,
     *     correlation_id?: string|null
     * } $attributes
     * @return array{item: ReviewItem, created: bool}
     */
    public function execute(array $attributes): array
    {
        $entityType = (string) $attributes['entity_type'];
        $entityId = (string) $attributes['entity_id'];
        $filingId = (string) $attributes['filing_id'];
        $expectedVersion = (string) $attributes['expected_version'];
        $rationale = trim((string) $attributes['rationale']);
        $idempotencyKey = (string) $attributes['idempotency_key'];

        if (! in_array($entityType, ['normalized_fact', 'validation_result'], true)) {
            throw new ReviewMutationException(
                'REVIEW_ENTITY_TYPE_UNSUPPORTED',
                'This entity type cannot be marked for review.',
                422,
                'entity_type',
            );
        }

        return DB::transaction(function () use (
            $entityType,
            $entityId,
            $filingId,
            $expectedVersion,
            $rationale,
            $idempotencyKey,
            $attributes,
        ): array {
            $entity = $this->findEntity($entityType, $entityId);
            if ($entity === null || (string) $entity->filing_id !== $filingId) {
                throw new ReviewMutationException(
                    'REVIEW_ENTITY_NOT_OWNED',
                    'The review entity does not belong to the selected filing.',
                    422,
                    'filing_id',
                );
            }

            $currentVersion = $this->versionFor($entity);
            if (! hash_equals($currentVersion, $expectedVersion)) {
                throw new ReviewMutationException(
                    'REVIEW_VERSION_STALE',
                    'The review item changed since it was loaded. Refresh and try again.',
                    409,
                    'expected_version',
                );
            }

            $activeIdentity = implode('|', [$entityType, $entityId, $filingId]);
            $existing = ReviewItem::query()
                ->where('active_identity', $activeIdentity)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->idempotency_key === $idempotencyKey && $existing->rationale === $rationale) {
                    return ['item' => $existing, 'created' => false];
                }

                throw new ReviewMutationException(
                    'REVIEW_ITEM_EXISTS',
                    'An active review item already exists for this entity.',
                    409,
                );
            }

            $item = ReviewItem::query()->create([
                'review_item_id' => 'REV-'.Str::uuid()->toString(),
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'filing_id' => $filingId,
                'status' => 'OPEN',
                'rationale' => $rationale,
                'created_by' => 'system',
                'expected_version' => $expectedVersion,
                'active_identity' => $activeIdentity,
                'idempotency_key' => $idempotencyKey,
            ]);

            AuditLog::query()->create([
                'actor_id' => 'system',
                'action' => 'review_item.marked',
                'entity_type' => ReviewItem::class,
                'entity_id' => $item->review_item_id,
                'old_value' => null,
                'new_value' => $this->auditValue($item),
                'rationale' => $rationale,
                'filing_id' => $filingId,
                'correlation_id' => $attributes['correlation_id'] ?? $idempotencyKey,
            ]);

            return ['item' => $item, 'created' => true];
        });
    }

    private function findEntity(string $entityType, string $entityId): ?Model
    {
        return match ($entityType) {
            'normalized_fact' => NormalizedFact::query()->find($entityId),
            'validation_result' => ValidationResult::query()->find($entityId),
            default => null,
        };
    }

    private function versionFor(Model $entity): string
    {
        if ($entity instanceof NormalizedFact) {
            return implode('|', [
                (string) $entity->normalization_version,
                (string) $entity->mapping_rule_id,
                (string) $entity->mapping_rule_version,
            ]);
        }

        if ($entity instanceof ValidationResult) {
            return implode('|', [
                (string) $entity->normalized_dataset_version,
                (string) $entity->validation_rule_set_version,
                (string) $entity->rule_code,
                (string) $entity->rule_version,
            ]);
        }

        return '';
    }

    /** @return array<string, mixed> */
    private function auditValue(ReviewItem $item): array
    {
        return [
            'review_item_id' => (string) $item->review_item_id,
            'entity_type' => (string) $item->entity_type,
            'entity_id' => (string) $item->entity_id,
            'filing_id' => (string) $item->filing_id,
            'status' => (string) $item->status,
            'rationale' => $item->rationale,
            'expected_version' => $item->expected_version,
            'created_by' => $item->created_by,
        ];
    }
}
