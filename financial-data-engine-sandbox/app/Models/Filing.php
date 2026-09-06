<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['filing_id', 'issuer_code', 'report_type', 'fiscal_year', 'fiscal_period', 'period_start', 'period_end', 'source_url', 'source_type', 'storage_path', 'source_hash', 'revision_number', 'supersedes_filing_id', 'discovered_at', 'downloaded_at'])]
class Filing extends Model
{
    protected $primaryKey = 'filing_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'revision_number' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'discovered_at' => 'datetime',
            'downloaded_at' => 'datetime',
        ];
    }

    public function supersededFiling(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_filing_id', 'filing_id');
    }

    public function filingsThatSupersedeThis(): HasMany
    {
        return $this->hasMany(self::class, 'supersedes_filing_id', 'filing_id');
    }

    public function xbrlContexts(): HasMany
    {
        return $this->hasMany(XbrlContext::class, 'filing_id', 'filing_id');
    }

    public function xbrlUnits(): HasMany
    {
        return $this->hasMany(XbrlUnit::class, 'filing_id', 'filing_id');
    }

    public function rawFacts(): HasMany
    {
        return $this->hasMany(RawFact::class, 'filing_id', 'filing_id');
    }

    public function normalizedFacts(): HasMany
    {
        return $this->hasMany(NormalizedFact::class, 'filing_id', 'filing_id');
    }

    public function validationResults(): HasMany
    {
        return $this->hasMany(ValidationResult::class, 'filing_id', 'filing_id');
    }

    public function financialMetrics(): HasMany
    {
        return $this->hasMany(FinancialMetric::class, 'filing_id', 'filing_id');
    }

    public function evidenceRecords(): HasMany
    {
        return $this->hasMany(EvidenceRecord::class, 'filing_id', 'filing_id');
    }

    public function debtRecords(): HasMany
    {
        return $this->hasMany(DebtRecord::class, 'filing_id', 'filing_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'filing_id', 'filing_id');
    }

    public function pipelineRun(): HasMany
    {
        return $this->hasMany(PipelineRun::class, 'filing_id', 'filing_id');
    }

    public function pipelineJobRuns(): HasMany
    {
        return $this->hasMany(PipelineJobRun::class, 'filing_id', 'filing_id');
    }
}
