<?php

namespace App\Domain\FinancialData\Discovery;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final readonly class DiscoveredFilingData
{
    public string $filingId;

    public string $issuerCode;

    public string $periodEnd;

    public string $sourceUrl;

    public string $sourceType;

    public string $sourceHash;

    public int $revisionNumber;

    public ?string $reportType;

    public ?int $fiscalYear;

    public ?string $fiscalPeriod;

    public ?string $periodStart;

    public ?string $storagePath;

    public ?string $supersedesFilingId;

    public ?string $discoveredAt;

    public function __construct(
        string $filingId,
        string $issuerCode,
        string $periodEnd,
        string $sourceUrl,
        string $sourceType,
        string $sourceHash,
        int $revisionNumber,
        ?string $reportType = null,
        ?int $fiscalYear = null,
        ?string $fiscalPeriod = null,
        ?string $periodStart = null,
        ?string $storagePath = null,
        ?string $supersedesFilingId = null,
        ?string $discoveredAt = null,
    ) {
        $this->filingId = trim($filingId);
        $this->issuerCode = strtoupper(trim($issuerCode));
        $this->periodEnd = trim($periodEnd);
        $this->sourceUrl = trim($sourceUrl);
        $this->sourceType = strtoupper(trim($sourceType));
        $this->sourceHash = trim($sourceHash);
        $this->revisionNumber = $revisionNumber;
        $this->reportType = $reportType === null ? null : strtoupper(trim($reportType));
        $this->fiscalYear = $fiscalYear;
        $this->fiscalPeriod = $fiscalPeriod === null ? null : trim($fiscalPeriod);
        $this->periodStart = $periodStart === null ? null : trim($periodStart);
        $this->storagePath = $storagePath === null ? null : trim($storagePath);
        $this->supersedesFilingId = $supersedesFilingId === null ? null : trim($supersedesFilingId);
        $this->discoveredAt = $discoveredAt === null ? null : self::normaliseDateTime($discoveredAt);

        self::assertIdentifier($this->filingId, 'filing identifier');
        self::assertIdentifier($this->issuerCode, 'issuer code', 32);
        self::assertDate($this->periodEnd, 'period end');

        if ($this->periodStart !== null) {
            self::assertDate($this->periodStart, 'period start');

            if ($this->periodStart > $this->periodEnd) {
                throw new InvalidArgumentException('Period start cannot be after period end.');
            }
        }

        self::assertUrl($this->sourceUrl);
        self::assertEnum($this->sourceType, ['XBRL_INSTANCE', 'INLINE_XBRL', 'XLSX', 'PDF', 'OTHER'], 'source type');

        if ($this->sourceHash === '' || strlen($this->sourceHash) > 128) {
            throw new InvalidArgumentException('Source hash is invalid.');
        }

        if ($this->revisionNumber < 1) {
            throw new InvalidArgumentException('Revision number must be positive.');
        }

        if ($this->reportType !== null) {
            self::assertEnum($this->reportType, ['ANNUAL', 'QUARTERLY', 'INTERIM', 'OTHER'], 'report type');
        }

        if ($this->fiscalYear !== null && ($this->fiscalYear < 1 || $this->fiscalYear > 9999)) {
            throw new InvalidArgumentException('Fiscal year is invalid.');
        }

        if ($this->fiscalPeriod !== null && ($this->fiscalPeriod === '' || strlen($this->fiscalPeriod) > 20 || preg_match('/\A[A-Za-z0-9._-]+\z/', $this->fiscalPeriod) !== 1)) {
            throw new InvalidArgumentException('Fiscal period is invalid.');
        }

        if ($this->supersedesFilingId !== null) {
            self::assertIdentifier($this->supersedesFilingId, 'superseded filing identifier');
        }

        if ($this->storagePath !== null) {
            self::assertRelativePath($this->storagePath);
        }

    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        $allowed = [
            'filing_id', 'issuer_code', 'report_type', 'fiscal_year', 'fiscal_period',
            'period_start', 'period_end', 'source_url', 'source_type', 'storage_path',
            'source_hash', 'revision_number', 'supersedes_filing_id', 'discovered_at',
        ];
        $unknown = array_diff(array_keys($attributes), $allowed);

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown filing metadata field.');
        }

        $required = ['filing_id', 'issuer_code', 'period_end', 'source_url', 'source_type', 'source_hash', 'revision_number'];

        foreach ($required as $field) {
            if (! array_key_exists($field, $attributes) || $attributes[$field] === null) {
                throw new InvalidArgumentException("Missing filing metadata field [{$field}].");
            }
        }

        foreach (['filing_id', 'issuer_code', 'period_end', 'source_url', 'source_type', 'source_hash'] as $field) {
            if (! is_string($attributes[$field])) {
                throw new InvalidArgumentException("Filing metadata field [{$field}] must be a string.");
            }
        }

        if (! is_int($attributes['revision_number'])) {
            throw new InvalidArgumentException('Revision number must be an integer.');
        }

        if (isset($attributes['fiscal_year']) && ! is_int($attributes['fiscal_year'])) {
            throw new InvalidArgumentException('Fiscal year must be an integer.');
        }

        return new self(
            filingId: trim($attributes['filing_id']),
            issuerCode: strtoupper(trim($attributes['issuer_code'])),
            periodEnd: trim($attributes['period_end']),
            sourceUrl: trim($attributes['source_url']),
            sourceType: strtoupper(trim($attributes['source_type'])),
            sourceHash: trim($attributes['source_hash']),
            revisionNumber: $attributes['revision_number'],
            reportType: self::nullableUpperString($attributes['report_type'] ?? null),
            fiscalYear: $attributes['fiscal_year'] ?? null,
            fiscalPeriod: self::nullableString($attributes['fiscal_period'] ?? null),
            periodStart: self::nullableString($attributes['period_start'] ?? null),
            storagePath: self::nullableString($attributes['storage_path'] ?? null),
            supersedesFilingId: self::nullableString($attributes['supersedes_filing_id'] ?? null),
            discoveredAt: self::nullableString($attributes['discovered_at'] ?? null),
        );
    }

    public function sourceLocator(): string
    {
        return $this->sourceUrl;
    }

    public function stableCandidateIdentity(): string
    {
        return $this->filingId.'@'.$this->revisionNumber;
    }

    /**
     * @return array<string, mixed>
     */
    public function toFilingAttributes(): array
    {
        return [
            'filing_id' => $this->filingId,
            'issuer_code' => $this->issuerCode,
            'report_type' => $this->reportType,
            'fiscal_year' => $this->fiscalYear,
            'fiscal_period' => $this->fiscalPeriod,
            'period_start' => $this->periodStart,
            'period_end' => $this->periodEnd,
            'source_url' => $this->sourceUrl,
            'source_type' => $this->sourceType,
            'storage_path' => $this->storagePath,
            'source_hash' => $this->sourceHash,
            'revision_number' => $this->revisionNumber,
            'supersedes_filing_id' => $this->supersedesFilingId,
            'discovered_at' => $this->discoveredAt,
            'processing_stage' => 'DISCOVERED',
            'quality_status' => 'PENDING',
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException('Optional filing metadata fields must be strings or null.');
        }

        return trim($value);
    }

    private static function nullableUpperString(mixed $value): ?string
    {
        $value = self::nullableString($value);

        return $value === null ? null : strtoupper($value);
    }

    private static function assertIdentifier(string $value, string $label, int $maxLength = 128): void
    {
        if (strlen($value) > $maxLength || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/', $value) !== 1) {
            throw new InvalidArgumentException("{$label} is invalid.");
        }
    }

    /**
     * @param  list<string>  $allowed
     */
    private static function assertEnum(string $value, array $allowed, string $label): void
    {
        if (! in_array($value, $allowed, true)) {
            throw new InvalidArgumentException("{$label} is invalid.");
        }
    }

    private static function assertDate(string $value, string $label): void
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException("{$label} is invalid.");
        }
    }

    private static function assertUrl(string $value): void
    {
        $parts = parse_url($value);

        if ($parts === false || ! isset($parts['scheme'], $parts['host']) || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true) || isset($parts['user'], $parts['pass'])) {
            throw new InvalidArgumentException('Source URL is invalid.');
        }

        if (isset($parts['port']) && ! in_array($parts['port'], [80, 443], true)) {
            throw new InvalidArgumentException('Source URL port is not allowed.');
        }
    }

    private static function assertRelativePath(string $value): void
    {
        $normalised = str_replace('\\', '/', trim($value));

        if ($normalised === '' || str_starts_with($normalised, '/') || preg_match('/(^|\/)\.\.(\/|$)/', $normalised) === 1 || preg_match('/[\x00-\x1F\x7F]/', $normalised) === 1) {
            throw new InvalidArgumentException('Storage path is invalid.');
        }
    }

    private static function normaliseDateTime(string $value): string
    {
        try {
            return (new DateTimeImmutable($value))->setTimezone(new \DateTimeZone('UTC'))->format(DateTimeInterface::ATOM);
        } catch (\Exception) {
            throw new InvalidArgumentException('Discovered timestamp is invalid.');
        }
    }
}
