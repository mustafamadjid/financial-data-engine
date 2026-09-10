<?php

namespace App\Domain\FinancialData\Integration;

use App\Domain\FinancialData\Integration\Exceptions\PublishedFilingNotFound;
use App\Domain\FinancialData\Integration\Exceptions\PublishedSnapshotNotFound;
use App\Models\PublishedSnapshot;
use Carbon\Carbon;

final class PublishedSnapshotSelector
{
    public function latestForFiling(string $filingId): PublishedSnapshot
    {
        $snapshot = PublishedSnapshot::query()
            ->where('filing_id', $filingId)
            ->orderByDesc('published_at')
            ->orderByDesc('snapshot_id')
            ->first();

        if ($snapshot === null) {
            throw new PublishedFilingNotFound;
        }

        return $snapshot;
    }

    public function exactSnapshot(string $snapshotId): PublishedSnapshot
    {
        $snapshot = PublishedSnapshot::query()->where('snapshot_id', $snapshotId)->first();

        if ($snapshot === null) {
            throw new PublishedSnapshotNotFound;
        }

        return $snapshot;
    }

    public function forIssuer(IssuerFilingQuery $query): CursorPage
    {
        $builder = PublishedSnapshot::query()
            ->where('payload->quality->status', 'VERIFIED')
            ->where('payload->filing->issuer_code', strtoupper($query->issuerCode))
            ->orderByDesc('published_at')
            ->orderByDesc('snapshot_id');

        foreach ([
            'report_type' => $query->reportType,
            'fiscal_year' => $query->fiscalYear,
            'fiscal_period' => $query->fiscalPeriod,
            'period_end' => $query->periodEnd,
        ] as $path => $value) {
            if ($value !== null) {
                $builder->where('payload->filing->'.$path, $value);
            }
        }
        if ($query->publishedAfter !== null) {
            $builder->where('published_at', '>', Carbon::parse($query->publishedAfter));
        }

        $latestByFiling = [];
        foreach ($builder->get() as $snapshot) {
            if (! isset($latestByFiling[$snapshot->filing_id])) {
                $latestByFiling[$snapshot->filing_id] = $snapshot;
            }
        }
        $snapshots = array_values($latestByFiling);

        $cursor = $query->cursor !== null
            ? (new PublishedSnapshotCursor)->decode($query->cursor, $query->filters())
            : null;
        if ($cursor !== null) {
            $snapshots = array_values(array_filter($snapshots, function (PublishedSnapshot $snapshot) use ($cursor): bool {
                $publishedAt = $snapshot->published_at instanceof Carbon
                    ? $snapshot->published_at->format('Y-m-d H:i:s')
                    : (string) $snapshot->published_at;

                return $publishedAt < $cursor['published_at']
                    || ($publishedAt === $cursor['published_at'] && (string) $snapshot->snapshot_id < $cursor['snapshot_id']);
            }));
        }

        usort($snapshots, fn (PublishedSnapshot $left, PublishedSnapshot $right): int => [
            $this->sortableTimestamp($right),
            (string) $right->snapshot_id,
        ] <=> [
            $this->sortableTimestamp($left),
            (string) $left->snapshot_id,
        ]);

        $hasMore = count($snapshots) > $query->limit;
        $items = array_slice($snapshots, 0, $query->limit);
        $nextCursor = null;
        if ($hasMore && $items !== []) {
            $last = $items[array_key_last($items)];
            $nextCursor = (new PublishedSnapshotCursor)->encode(
                $last->published_at,
                (string) $last->snapshot_id,
                $query->filters(),
            );
        }

        return new CursorPage($items, $nextCursor, $hasMore, $query->limit);
    }

    private function sortableTimestamp(PublishedSnapshot $snapshot): string
    {
        return $snapshot->published_at instanceof Carbon
            ? $snapshot->published_at->format('Y-m-d H:i:s')
            : (string) $snapshot->published_at;
    }
}
