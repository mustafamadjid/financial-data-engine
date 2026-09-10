<?php

namespace App\Http\Responses\Api\V1;

use App\Domain\FinancialData\Integration\PublishedFilingDocument;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CanonicalJsonResponse
{
    public static function fromDocument(
        PublishedFilingDocument $document,
        Request $request,
        string $disposition = 'inline',
        ?string $filename = null,
    ): Response {
        $etag = $document->etag();
        if (trim((string) $request->header('If-None-Match'), '"') === $etag) {
            return response('', Response::HTTP_NOT_MODIFIED, [
                'ETag' => $etag,
                'X-HISSA-Contract-Version' => (string) config('api.contract_version', '0.1.0'),
                'X-Request-ID' => (string) $request->attributes->get('api_request_id', 'unknown'),
            ]);
        }

        $headers = [
            'Content-Type' => 'application/json',
            'ETag' => $etag,
            'X-HISSA-Contract-Version' => (string) config('api.contract_version', '0.1.0'),
            'X-Request-ID' => (string) $request->attributes->get('api_request_id', 'unknown'),
        ];
        if ($disposition === 'attachment' && $filename !== null) {
            $headers['Content-Disposition'] = 'attachment; filename="'.$filename.'"';
        }

        return response($document->canonicalJson(), Response::HTTP_OK, $headers);
    }
}
