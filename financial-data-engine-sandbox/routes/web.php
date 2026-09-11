<?php

use App\Http\Controllers\Ops\ConceptMappingDataController;
use App\Http\Controllers\Ops\ConceptMappingPageController;
use App\Http\Controllers\Ops\CreateMappingVersionController;
use App\Http\Controllers\Ops\FinancialFactController;
use App\Http\Controllers\Ops\FinancialReviewPageController;
use App\Http\Controllers\Ops\PipelineDataController;
use App\Http\Controllers\Ops\PipelineFilingDetailController;
use App\Http\Controllers\Ops\PipelinePageController;
use App\Http\Controllers\Ops\PipelineReprocessController;
use App\Http\Controllers\Ops\PipelineRetryController;
use App\Http\Controllers\Ops\ReprocessAffectedFilingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('ops')->name('ops.')->group(function (): void {
    Route::get('/pipeline', PipelinePageController::class)->name('pipeline');
    Route::get('/financial-review', FinancialReviewPageController::class)->name('financial-review');
    Route::get('/concept-mappings', ConceptMappingPageController::class)->name('concept-mappings');

    Route::prefix('data')->name('data.')->group(function (): void {
        Route::get('/pipeline-filings', [PipelineDataController::class, 'index'])->name('pipeline-filings.index');
        Route::get('/pipeline-summary', [PipelineDataController::class, 'summary'])->name('pipeline-summary');
        Route::get('/financial-facts', [FinancialFactController::class, 'index'])->name('financial-facts.index');
        Route::get('/financial-facts/{normalizedFact}', [FinancialFactController::class, 'show'])->name('financial-facts.show');
        Route::get('/concept-mappings/canonical-options', [ConceptMappingDataController::class, 'canonicalOptions'])->name('concept-mappings.canonical-options');
        Route::get('/concept-mappings/{mappingSeries}/history', [ConceptMappingDataController::class, 'history'])->name('concept-mappings.history');
        Route::get('/concept-mappings/{mappingSeries}/impact', [ConceptMappingDataController::class, 'impact'])->name('concept-mappings.impact');
        Route::get('/concept-mappings', [ConceptMappingDataController::class, 'index'])->name('concept-mappings.index');
        Route::get('/pipeline-filings/{filing}/history', [PipelineFilingDetailController::class, 'history'])->name('pipeline-filings.history');
        Route::get('/pipeline-filings/{filing}/artifacts/{artifact}', [PipelineFilingDetailController::class, 'artifact'])->name('pipeline-filings.artifacts.show');
        Route::get('/pipeline-filings/{filing}', [PipelineFilingDetailController::class, 'show'])->name('pipeline-filings.show');
    });
    Route::post('/actions/pipeline-job-runs/{jobRun}/retry', PipelineRetryController::class)->name('actions.pipeline-job-runs.retry');
    Route::post('/actions/pipeline-filings/{filing}/reprocess', PipelineReprocessController::class)->name('actions.pipeline-filings.reprocess');
    Route::post('/actions/concept-mappings/{mappingSeries}/versions', CreateMappingVersionController::class)->name('actions.concept-mappings.versions.store');
    Route::post('/actions/concept-mappings/{mappingSeries}/reprocess', ReprocessAffectedFilingsController::class)->name('actions.concept-mappings.reprocess');
});
