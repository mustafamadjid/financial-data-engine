<?php

namespace App\Domain\FinancialData\Discovery;

use InvalidArgumentException;

final readonly class DiscoveryCriteria
{
    public string $sourceAdapter;

    public string $discoveryWindow;

    public ?string $issuerCode;

    public ?string $cursor;

    public int $pageSize;

    public function __construct(
        string $sourceAdapter = 'configured',
        string $discoveryWindow = 'current',
        ?string $issuerCode = null,
        ?string $cursor = null,
        int $pageSize = 100,
    ) {
        $sourceAdapter = trim($sourceAdapter);
        $discoveryWindow = trim($discoveryWindow);
        $issuerCode = $issuerCode === null ? null : strtoupper(trim($issuerCode));
        $cursor = $cursor === null ? null : trim($cursor);

        if ($sourceAdapter === '' || preg_match('/\A[a-z0-9][a-z0-9._-]{0,63}\z/', $sourceAdapter) !== 1) {
            throw new InvalidArgumentException('Discovery source adapter is invalid.');
        }

        if ($discoveryWindow === '' || strlen($discoveryWindow) > 100 || preg_match('/[\x00-\x1F\x7F]/', $discoveryWindow) === 1) {
            throw new InvalidArgumentException('Discovery window is invalid.');
        }

        if ($issuerCode !== null && preg_match('/\A[A-Z0-9.-]{1,32}\z/', $issuerCode) !== 1) {
            throw new InvalidArgumentException('Issuer code filter is invalid.');
        }

        if ($cursor !== null && ($cursor === '' || strlen($cursor) > 512 || preg_match('/[\x00-\x1F\x7F]/', $cursor) === 1)) {
            throw new InvalidArgumentException('Discovery cursor is invalid.');
        }

        if ($pageSize < 1 || $pageSize > 1000) {
            throw new InvalidArgumentException('Discovery page size must be between 1 and 1000.');
        }

        $this->sourceAdapter = $sourceAdapter;
        $this->discoveryWindow = $discoveryWindow;
        $this->issuerCode = $issuerCode;
        $this->cursor = $cursor;
        $this->pageSize = $pageSize;
    }
}
