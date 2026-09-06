<?php

namespace App\Infrastructure\Storage;

use App\Domain\FinancialData\Download\Exceptions\TerminalDownloadException;
use App\Models\Filing;
use App\Models\FilingArtifact;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class FilingArtifactStorage
{
    public function storeTemporary(string $filingId, string $filename, string $contents): string
    {
        $this->assertSafeSegment($filingId, 'filing identifier');
        $filename = $this->safeFilename($filename);
        $path = trim((string) config('financial-pipeline.storage.temporary_path', 'financial-pipeline/tmp'), '/').'/'.$filingId.'/'.Str::uuid().'-'.$filename;

        if (! $this->disk()->put($path, $contents)) {
            throw new TerminalDownloadException('The temporary filing artifact could not be stored.');
        }

        return $path;
    }

    public function persist(Filing $filing, string $temporaryPath, string $artifactType, ?string $originalFilename = null, ?string $contentType = null): FilingArtifact
    {
        $temporaryPath = $this->safePath($temporaryPath);
        $artifactType = strtoupper(trim($artifactType));

        if ($artifactType === '' || strlen($artifactType) > 50) {
            throw new TerminalDownloadException('The filing artifact type is invalid.');
        }

        if (! $this->disk()->exists($temporaryPath)) {
            throw new TerminalDownloadException('The temporary filing artifact does not exist.');
        }

        $contents = $this->disk()->get($temporaryPath);
        $size = strlen($contents);

        if ($size === 0) {
            throw new TerminalDownloadException('The filing artifact is empty.');
        }

        if ($size > (int) config('financial-pipeline.download.max_bytes', 50 * 1024 * 1024)) {
            throw new TerminalDownloadException('The filing artifact exceeds the configured size limit.');
        }

        $sourceHash = hash('sha256', $contents);
        $existing = FilingArtifact::query()
            ->where('filing_id', $filing->filing_id)
            ->where('artifact_type', $artifactType)
            ->where('source_hash', $sourceHash)
            ->first();

        if ($existing !== null && $this->disk()->exists($existing->storage_path)) {
            $this->disk()->delete($temporaryPath);
            $this->syncFiling($filing, $existing->storage_path, $sourceHash);

            return $existing;
        }

        if ($existing !== null) {
            throw new TerminalDownloadException('Artifact metadata exists but its stable source is missing.');
        }

        $filename = $this->safeFilename($originalFilename ?? basename($temporaryPath));
        $stablePath = $this->stablePath($filing, $sourceHash, $filename);

        if ($this->disk()->exists($stablePath)) {
            throw new TerminalDownloadException('The stable filing artifact path already exists without matching metadata.');
        }

        if (! $this->disk()->move($temporaryPath, $stablePath)) {
            throw new TerminalDownloadException('The filing artifact could not be moved to stable storage.');
        }

        $artifact = FilingArtifact::query()->create([
            'artifact_id' => 'ART-'.hash('sha256', $filing->filing_id.'|'.$artifactType.'|'.$sourceHash),
            'filing_id' => $filing->filing_id,
            'artifact_type' => $artifactType,
            'source_hash' => $sourceHash,
            'storage_path' => $stablePath,
            'original_filename' => $filename,
            'content_type' => $contentType,
            'size_bytes' => $size,
            'downloaded_at' => now(),
        ]);

        $this->syncFiling($filing, $stablePath, $sourceHash);

        return $artifact;
    }

    public function validArtifact(Filing $filing, string $artifactType): ?FilingArtifact
    {
        $artifact = FilingArtifact::query()
            ->where('filing_id', $filing->filing_id)
            ->where('artifact_type', strtoupper(trim($artifactType)))
            ->latest('downloaded_at')
            ->first();

        return $artifact !== null && $this->disk()->exists($artifact->storage_path) ? $artifact : null;
    }

    private function stablePath(Filing $filing, string $sourceHash, string $filename): string
    {
        $periodEnd = $filing->period_end instanceof \DateTimeInterface
            ? $filing->period_end->format('Y-m-d')
            : (string) $filing->period_end;

        return trim((string) config('financial-pipeline.storage.artifacts_path', 'financial-pipeline/artifacts'), '/').'/'.strtoupper($filing->issuer_code).'/'.$periodEnd.'/'.$filing->revision_number.'/'.$sourceHash.'/'.$filename;
    }

    private function syncFiling(Filing $filing, string $storagePath, string $sourceHash): void
    {
        $filing->forceFill([
            'storage_path' => $storagePath,
            'source_hash' => $sourceHash,
            'downloaded_at' => now(),
        ])->save();
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk((string) config('financial-pipeline.storage.disk', 'local'));
    }

    private function safeFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', trim($filename)));
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?? '';

        if ($filename === '' || $filename === '.' || $filename === '..') {
            throw new TerminalDownloadException('The filing artifact filename is invalid.');
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ($extension !== '' && ! in_array($extension, (array) config('financial-pipeline.download.allowed_extensions', []), true)) {
            throw new TerminalDownloadException('The filing artifact extension is not supported.');
        }

        return $filename;
    }

    private function safePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));

        if ($path === '' || str_starts_with($path, '/') || preg_match('/(^|\/)\.\.(\/|$)/', $path) === 1 || preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            throw new TerminalDownloadException('The temporary filing path is invalid.');
        }

        return $path;
    }

    private function assertSafeSegment(string $value, string $label): void
    {
        if ($value === '' || strlen($value) > 128 || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/', $value) !== 1) {
            throw new TerminalDownloadException("The {$label} is invalid.");
        }
    }
}
