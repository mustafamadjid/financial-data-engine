<?php

namespace App\Infrastructure\Discovery;

use App\Domain\FinancialData\Discovery\Contracts\FilingDiscoverySource as LegacyFilingDiscoverySource;
use App\Domain\FinancialData\Discovery\DiscoveredFilingData;
use App\Domain\FinancialData\Discovery\DiscoveryCriteria;
use App\Domain\FinancialData\Pipeline\Contracts\FilingDiscoverySource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

final class ConfiguredFilingDiscoverySource implements FilingDiscoverySource, LegacyFilingDiscoverySource
{
    public function discover(DiscoveryCriteria $criteria): iterable
    {
        if ($criteria->sourceAdapter !== 'configured') {
            throw new InvalidArgumentException('The configured discovery source requires adapter [configured].');
        }

        $diskName = $this->validatedDiskName((string) config('financial-pipeline.discovery.disk', config('financial-pipeline.storage.disk', 'local')));
        $fixturePath = $this->validatedFixturePath((string) config('financial-pipeline.discovery.fixture_path', 'financial-pipeline/discovery/candidates.json'));
        $disk = Storage::disk($diskName);

        if (! $disk->exists($fixturePath)) {
            throw new InvalidArgumentException('Configured discovery fixture does not exist.');
        }

        $maxBytes = (int) config('financial-pipeline.discovery.max_bytes', 5 * 1024 * 1024);

        if ($disk->size($fixturePath) > $maxBytes) {
            throw new InvalidArgumentException('Configured discovery fixture exceeds the maximum size.');
        }

        try {
            $decoded = json_decode($disk->get($fixturePath), true, 32, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('Configured discovery fixture is not valid JSON.', previous: $exception);
        }

        $records = $this->candidateRecords($decoded);
        $maxCandidates = (int) config('financial-pipeline.discovery.max_candidates', 1000);

        if (count($records) > $maxCandidates) {
            throw new InvalidArgumentException('Configured discovery fixture exceeds the maximum candidate count.');
        }

        $offset = $this->decodeCursor($criteria->cursor);
        $yielded = 0;

        foreach ($records as $index => $record) {
            if (! is_array($record)) {
                $this->logRejectedCandidate($index, 'Candidate must be an object.');

                continue;
            }

            try {
                $candidate = DiscoveredFilingData::fromArray($record);
                $this->assertAllowedSource($candidate->sourceUrl);
            } catch (Throwable $exception) {
                $this->logRejectedCandidate($index, $exception->getMessage());

                continue;
            }

            if ($criteria->issuerCode !== null && $candidate->issuerCode !== $criteria->issuerCode) {
                continue;
            }

            if ($offset > 0) {
                $offset--;

                continue;
            }

            yield $candidate;
            $yielded++;

            if ($yielded >= $criteria->pageSize) {
                break;
            }
        }
    }

    /**
     * @return list<array<mixed>>
     */
    private function candidateRecords(mixed $decoded): array
    {
        if (is_array($decoded) && array_key_exists('candidates', $decoded)) {
            if (count(array_diff(array_keys($decoded), ['candidates'])) > 0 || ! is_array($decoded['candidates']) || ! array_is_list($decoded['candidates'])) {
                throw new InvalidArgumentException('Configured discovery fixture envelope is invalid.');
            }

            return $decoded['candidates'];
        }

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw new InvalidArgumentException('Configured discovery fixture must contain a candidate list.');
        }

        return $decoded;
    }

    private function validatedDiskName(string $diskName): string
    {
        if ($diskName === '' || preg_match('/\A[A-Za-z0-9._-]+\z/', $diskName) !== 1) {
            throw new InvalidArgumentException('Discovery storage disk is invalid.');
        }

        return $diskName;
    }

    private function validatedFixturePath(string $fixturePath): string
    {
        $fixturePath = str_replace('\\', '/', trim($fixturePath));

        if ($fixturePath === '' || str_starts_with($fixturePath, '/') || preg_match('/(^|\/)\.\.(\/|$)/', $fixturePath) === 1 || preg_match('/[\x00-\x1F\x7F]/', $fixturePath) === 1 || ! str_ends_with(strtolower($fixturePath), '.json')) {
            throw new InvalidArgumentException('Discovery fixture path is invalid.');
        }

        return $fixturePath;
    }

    private function decodeCursor(?string $cursor): int
    {
        if ($cursor === null) {
            return 0;
        }

        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);

        if ($decoded === false || ! ctype_digit($decoded)) {
            throw new InvalidArgumentException('Discovery cursor is invalid for the configured source.');
        }

        return (int) $decoded;
    }

    private function assertAllowedSource(string $sourceUrl): void
    {
        $parts = parse_url($sourceUrl);
        $allowedHosts = array_map('strtolower', array_filter((array) config('financial-pipeline.discovery.allowed_hosts', ['example.test'])));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($host === '' || ! in_array($host, $allowedHosts, true)) {
            throw new InvalidArgumentException('Source URL host is not allowlisted.');
        }

        if (in_array($host, ['localhost', 'localhost.localdomain'], true) || str_ends_with($host, '.localhost')) {
            throw new InvalidArgumentException('Local source URL hosts are not allowed.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            throw new InvalidArgumentException('Private or reserved source URL hosts are not allowed.');
        }

        if (isset($parts['port']) && ! in_array($parts['port'], [80, 443], true)) {
            throw new InvalidArgumentException('Source URL port is not allowlisted.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            throw new InvalidArgumentException('Private or reserved source URL hosts are not allowed.');
        }
    }

    private function logRejectedCandidate(int $index, string $reason): void
    {
        Log::warning('pipeline.discovery.candidate_rejected', [
            'source_adapter' => 'configured',
            'candidate_index' => $index,
            'reason' => $reason,
        ]);
    }
}
