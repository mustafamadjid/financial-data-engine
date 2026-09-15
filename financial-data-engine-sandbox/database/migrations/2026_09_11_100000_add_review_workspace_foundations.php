<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('filings', function (Blueprint $table): void {
            if (! Schema::hasColumn('filings', 'taxonomy_entry_point')) {
                $table->string('taxonomy_entry_point', 255)->nullable()->after('source_type');
                $table->index('taxonomy_entry_point');
            }
        });

        Schema::table('concept_mappings', function (Blueprint $table): void {
            $table->string('mapping_series_key', 128)->nullable()->after('mapping_rule_id');
            $table->string('supersedes_mapping_rule_id', 128)->nullable()->after('mapping_series_key');
            $table->json('evidence_ids')->nullable()->after('reviewer');
            $table->string('created_by', 128)->default('system')->after('evidence_ids');
        });

        $mappings = DB::table('concept_mappings')
            ->select(['mapping_rule_id', 'source_concept', 'entry_point', 'rule_version'])
            ->orderBy('source_concept')
            ->orderBy('entry_point')
            ->orderBy('rule_version')
            ->orderBy('mapping_rule_id')
            ->get();
        $seriesByVersion = [];

        foreach ($mappings as $mapping) {
            $seriesKey = $this->mappingSeriesKey((string) $mapping->source_concept, $mapping->entry_point === null ? null : (string) $mapping->entry_point);
            $identity = $seriesKey.'|'.(int) $mapping->rule_version;

            if (isset($seriesByVersion[$identity])) {
                throw new RuntimeException("Mapping version collision detected for [{$identity}].");
            }

            $seriesByVersion[$identity] = (string) $mapping->mapping_rule_id;
            DB::table('concept_mappings')
                ->where('mapping_rule_id', $mapping->mapping_rule_id)
                ->update(['mapping_series_key' => $seriesKey]);
        }

        foreach ($mappings as $mapping) {
            $seriesKey = $this->mappingSeriesKey((string) $mapping->source_concept, $mapping->entry_point === null ? null : (string) $mapping->entry_point);
            $previous = DB::table('concept_mappings')
                ->where('mapping_series_key', $seriesKey)
                ->where('rule_version', '<', (int) $mapping->rule_version)
                ->orderByDesc('rule_version')
                ->orderByDesc('mapping_rule_id')
                ->value('mapping_rule_id');

            if ($previous !== null) {
                DB::table('concept_mappings')
                    ->where('mapping_rule_id', $mapping->mapping_rule_id)
                    ->update(['supersedes_mapping_rule_id' => $previous]);
            }
        }

        Schema::table('concept_mappings', function (Blueprint $table): void {
            $table->unique(['mapping_series_key', 'rule_version'], 'concept_mappings_series_version_unique');
            $table->index(['mapping_series_key', 'status', 'rule_version'], 'concept_mappings_series_status_version_idx');
        });

        Schema::create('review_items', function (Blueprint $table): void {
            $table->string('review_item_id', 128)->primary();
            $table->string('entity_type', 100);
            $table->string('entity_id', 128);
            $table->string('filing_id', 128);
            $table->string('status', 30);
            $table->text('rationale');
            $table->string('created_by', 128)->default('system');
            $table->string('resolved_by', 128)->nullable();
            $table->text('resolution_rationale')->nullable();
            $table->string('expected_version', 128)->nullable();
            $table->string('active_identity', 255)->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('filing_id')->references('filing_id')->on('filings')->restrictOnDelete();
            $table->unique('active_identity', 'review_items_active_identity_unique');
            $table->index(['filing_id', 'status']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_items');

        Schema::table('concept_mappings', function (Blueprint $table): void {
            $table->dropUnique('concept_mappings_series_version_unique');
            $table->dropIndex('concept_mappings_series_status_version_idx');
            $table->dropColumn(['mapping_series_key', 'supersedes_mapping_rule_id', 'evidence_ids', 'created_by']);
        });

        Schema::table('filings', function (Blueprint $table): void {
            $table->dropIndex('filings_taxonomy_entry_point_index');
            $table->dropColumn('taxonomy_entry_point');
        });
    }

    private function mappingSeriesKey(string $sourceConcept, ?string $entryPoint): string
    {
        return substr(hash('sha256', trim($sourceConcept).'|'.trim((string) $entryPoint)), 0, 64);
    }
};
