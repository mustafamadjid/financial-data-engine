<?php

namespace App\Http\Controllers\Ops;

use App\Application\Ops\Pipeline\PipelineFilingDetailQuery;
use App\Application\Ops\Pipeline\PipelineFilingHistoryQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ops\PipelineHistoryRequest;
use App\Models\Filing;
use App\Models\FilingArtifact;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PipelineFilingDetailController extends Controller
{
    public function show(Filing $filing, PipelineFilingDetailQuery $detailQuery): JsonResponse
    {
        Gate::authorize('viewDetail', $filing);

        return response()->json(['data' => $detailQuery->detail($filing)]);
    }

    public function history(Filing $filing, PipelineHistoryRequest $request, PipelineFilingHistoryQuery $historyQuery): JsonResponse
    {
        Gate::authorize('viewHistory', $filing);
        $pagination = $request->pagination();
        $paginator = $historyQuery->paginate($filing, $pagination['per_page']);

        return response()->json([
            'data' => collect($paginator->items())
                ->map(fn (object $entry): array => $historyQuery->resource($entry))
                ->all(),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'lastPage' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function artifact(Filing $filing, FilingArtifact $artifact): StreamedResponse
    {
        abort_unless($artifact->filing_id === $filing->filing_id, 404);
        Gate::authorize('viewArtifact', [$filing, $artifact]);

        $disk = Storage::disk((string) config('financial-pipeline.storage.disk', 'local'));
        abort_unless($disk->exists($artifact->storage_path), 404);
        abort_unless(hash('sha256', $disk->get($artifact->storage_path)) === $artifact->source_hash, 404);

        return $disk->download(
            $artifact->storage_path,
            $this->safeFilename($artifact->original_filename),
            ['Content-Type' => $this->safeContentType($artifact->content_type)],
        );
    }

    private function safeFilename(string $filename): string
    {
        return preg_replace('/[^A-Za-z0-9._-]/', '_', basename(str_replace('\\', '/', $filename))) ?: 'artifact.bin';
    }

    private function safeContentType(?string $contentType): string
    {
        return $contentType !== null && preg_match('/\A[-\w.+]+\/[-\w.+]+\z/', $contentType) === 1
            ? $contentType
            : 'application/octet-stream';
    }
}
