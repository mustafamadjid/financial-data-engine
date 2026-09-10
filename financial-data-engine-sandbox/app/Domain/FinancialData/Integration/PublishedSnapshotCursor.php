<?php

namespace App\Domain\FinancialData\Integration;

use App\Domain\FinancialData\Integration\Exceptions\InvalidIntegrationQuery;
use Carbon\Carbon;
use DateTimeInterface;

final class PublishedSnapshotCursor
{
    public function __construct(private readonly ?string $applicationKey = null) {}

    /** @param array<string, scalar|null> $filters */
    public function encode(DateTimeInterface|string $publishedAt, string $snapshotId, array $filters): string
    {
        $payload = [
            'v' => 1,
            'published_at' => $publishedAt instanceof DateTimeInterface ? $publishedAt->format('Y-m-d H:i:s') : $publishedAt,
            'snapshot_id' => $snapshotId,
            'query_hash' => $this->queryHash($filters),
            'iat' => time(),
        ];
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $encodedPayload, $this->signingKey(), true);

        return $encodedPayload.'.'.$this->base64UrlEncode($signature);
    }

    /** @param array<string, scalar|null> $filters @return array{published_at: string, snapshot_id: string} */
    public function decode(string $cursor, array $filters): array
    {
        $parts = explode('.', $cursor);
        if (count($parts) !== 2) {
            throw new InvalidIntegrationQuery('Cursor is invalid.');
        }

        [$encodedPayload, $encodedSignature] = $parts;
        $expectedSignature = hash_hmac('sha256', $encodedPayload, $this->signingKey(), true);
        $signature = $this->base64UrlDecode($encodedSignature);
        if ($signature === null || ! hash_equals($expectedSignature, $signature)) {
            throw new InvalidIntegrationQuery('Cursor is invalid.');
        }

        $payload = json_decode((string) $this->base64UrlDecode($encodedPayload), true);
        if (! is_array($payload) || ($payload['v'] ?? null) !== 1 || ! is_string($payload['published_at'] ?? null) || ! is_string($payload['snapshot_id'] ?? null) || ! is_int($payload['iat'] ?? null) || abs(time() - $payload['iat']) > 86400) {
            throw new InvalidIntegrationQuery('Cursor is invalid.');
        }
        try {
            Carbon::parse($payload['published_at']);
        } catch (\Throwable) {
            throw new InvalidIntegrationQuery('Cursor is invalid.');
        }
        if (! hash_equals((string) ($payload['query_hash'] ?? ''), $this->queryHash($filters))) {
            throw new InvalidIntegrationQuery('Cursor does not match the query.');
        }

        return ['published_at' => $payload['published_at'], 'snapshot_id' => $payload['snapshot_id']];
    }

    public static function normalizeLimit(mixed $limit): int
    {
        if ($limit === null || $limit === '') {
            return 25;
        }
        if (filter_var($limit, FILTER_VALIDATE_INT) === false || (int) $limit < 1 || (int) $limit > 100) {
            throw new InvalidIntegrationQuery('Limit is invalid.');
        }

        return (int) $limit;
    }

    /** @param array<string, scalar|null> $filters */
    private function queryHash(array $filters): string
    {
        if (isset($filters['issuer_code']) && is_string($filters['issuer_code'])) {
            $filters['issuer_code'] = strtoupper($filters['issuer_code']);
        }
        if (array_key_exists('limit', $filters)) {
            $filters['limit'] = self::normalizeLimit($filters['limit']);
        }
        ksort($filters);

        return hash('sha256', json_encode($filters, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private function signingKey(): string
    {
        $applicationKey = $this->applicationKey ?? (string) config('app.key', 'integration-test-key');

        return hash_hmac('sha256', 'hissa-integration-cursor-v1', $applicationKey, true);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
