<?php

use App\Http\Controllers\Ops\PipelineDataController;
use App\Http\Controllers\Ops\PipelineFilingDetailController;
use App\Http\Controllers\Ops\PipelinePageController;
use App\Http\Controllers\Ops\PipelineReprocessController;
use App\Http\Controllers\Ops\PipelineRetryController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->prefix('ops')->name('ops.')->group(function (): void {
    Route::get('/pipeline', PipelinePageController::class)->name('pipeline');

    Route::prefix('data')->name('data.')->group(function (): void {
        Route::get('/pipeline-filings', [PipelineDataController::class, 'index'])->name('pipeline-filings.index');
        Route::get('/pipeline-summary', [PipelineDataController::class, 'summary'])->name('pipeline-summary');
        Route::get('/pipeline-filings/{filing}/history', [PipelineFilingDetailController::class, 'history'])->name('pipeline-filings.history');
        Route::get('/pipeline-filings/{filing}/artifacts/{artifact}', [PipelineFilingDetailController::class, 'artifact'])->name('pipeline-filings.artifacts.show');
        Route::get('/pipeline-filings/{filing}', [PipelineFilingDetailController::class, 'show'])->name('pipeline-filings.show');
    });
    Route::post('/actions/pipeline-job-runs/{jobRun}/retry', PipelineRetryController::class)->name('actions.pipeline-job-runs.retry');
    Route::post('/actions/pipeline-filings/{filing}/reprocess', PipelineReprocessController::class)->name('actions.pipeline-filings.reprocess');
});
