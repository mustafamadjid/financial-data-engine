<?php

use App\Http\Controllers\Api\V1\IssuerPublishedFilingController;
use App\Http\Controllers\Api\V1\PublishedFilingController;
use App\Http\Controllers\Api\V1\PublishedSnapshotController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->name('api.v1.')
    ->middleware(['assign.api.request.id', 'throttle:public-api'])
    ->group(function (): void {
        Route::get('/filings/{filing_id}', [PublishedFilingController::class, 'show'])
            ->where('filing_id', '[^/]{1,128}')
            ->name('filings.show');
        Route::get('/snapshots/{snapshot_id}', [PublishedSnapshotController::class, 'show'])
            ->where('snapshot_id', '[^/]{1,128}')
            ->name('snapshots.show');
        Route::get('/issuers/{issuer_code}/filings', [IssuerPublishedFilingController::class, 'index'])
            ->where('issuer_code', '[^/]{1,32}')
            ->name('issuers.filings.index');
        Route::get('/filings/{filing_id}/export', [PublishedFilingController::class, 'export'])
            ->where('filing_id', '[^/]{1,128}')
            ->name('filings.export');
    });
