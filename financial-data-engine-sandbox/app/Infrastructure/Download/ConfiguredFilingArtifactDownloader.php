<?php

namespace App\Infrastructure\Download;

use App\Domain\FinancialData\Download\Contracts\FilingArtifactDownloader;
use App\Domain\FinancialData\Download\DownloadedArtifact;
use App\Domain\FinancialData\Download\Exceptions\RetryableDownloadException;
use App\Domain\FinancialData\Download\Exceptions\TerminalDownloadException;
use App\Infrastructure\Storage\FilingArtifactStorage;
use App\Models\Filing;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

final class ConfiguredFilingArtifactDownloader implements FilingArtifactDownloader
{
    public function __construct(private readonly FilingArtifactStorage $storage) {}

    public function download(Filing $filing): DownloadedArtifact
    {
        $url = trim((string) $filing->source_url);
        $this->assertAllowedLocator($url);

        try {
            $response = Http::timeout((int) config('financial-pipeline.download.http_timeout', 30))
                ->connectTimeout((int) config('financial-pipeline.download.connect_timeout', 10))
                ->withOptions(['allow_redirects' => false])
                ->get($url);
        } catch (ConnectionException $exception) {
            throw new RetryableDownloadException('The filing source temporarily could not be reached.', 0, $exception);
        } catch (Throwable $exception) {
            throw new RetryableDownloadException('The filing source request failed temporarily.', 0, $exception);
        }

        $status = $response->status();

        if ($status === 408 || $status === 425 || $status === 429 || $status >= 500) {
            throw new RetryableDownloadException("The filing source returned retryable status [{$status}].");
        }

        if (! $response->successful()) {
            throw new TerminalDownloadException("The filing source returned terminal status [{$status}].");
        }

        $body = $response->body();
        $size = strlen($body);
        $maxBytes = (int) config('financial-pipeline.download.max_bytes', 50 * 1024 * 1024);

        if ($size === 0) {
            throw new TerminalDownloadException('The filing source returned an empty artifact.');
        }

        if ($size > $maxBytes) {
            throw new TerminalDownloadException('The filing artifact exceeds the configured size limit.');
        }

        $contentType = $response->header('Content-Type');
        $contentType = $contentType === null ? null : strtolower(trim(explode(';', $contentType, 2)[0]));

        if ($contentType !== null && ! in_array($contentType, (array) config('financial-pipeline.download.allowed_content_types', []), true)) {
            throw new TerminalDownloadException('The filing artifact content type is not supported.');
        }

        $filename = $this->filenameFromUrl($url, $filing->filing_id);
        $temporaryPath = $this->storage->storeTemporary($filing->filing_id, $filename, $body);

        return new DownloadedArtifact($temporaryPath, $filename, $contentType, $size);
    }

    private function assertAllowedLocator(string $url): void
    {
        $parts = parse_url($url);
        $allowedHosts = array_map('strtolower', (array) config('financial-pipeline.download.allowed_hosts', []));

        if ($parts === false || ! isset($parts['scheme'], $parts['host']) || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true) || ! in_array(strtolower($parts['host']), $allowedHosts, true) || (isset($parts['port']) && ! in_array($parts['port'], [80, 443], true))) {
            throw new TerminalDownloadException('The filing source locator is not allowed.');
        }
    }

    private function filenameFromUrl(string $url, string $filingId): string
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $filename = basename(rawurldecode($path));
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?? '';

        if ($filename === '' || $filename === '.' || $filename === '..') {
            $filename = $filingId.'.artifact';
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (! in_array($extension, (array) config('financial-pipeline.download.allowed_extensions', []), true)) {
            throw new TerminalDownloadException('The filing artifact extension is not supported.');
        }

        return $filename;
    }
}
