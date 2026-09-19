<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('filings', function (Blueprint $table): void {
            $table->string('external_filing_id', 255)->nullable()->after('filing_id');
            $table->string('revision_chain_id', 255)->nullable()->after('supersedes_filing_id');
            $table->dateTimeTz('publication_timestamp')->nullable()->after('discovered_at');
            $table->boolean('official_source_available')->default(false)->after('publication_timestamp');
            $table->text('official_pdf_url')->nullable()->after('official_source_available');
            $table->text('official_xlsx_url')->nullable()->after('official_pdf_url');
            $table->text('official_ixbrl_url')->nullable()->after('official_xlsx_url');
            $table->index(['issuer_code', 'period_end', 'revision_number'], 'filings_revision_selector_idx');
            $table->index('external_filing_id');
        });
    }

    public function down(): void
    {
        Schema::table('filings', function (Blueprint $table): void {
            $table->dropIndex('filings_revision_selector_idx');
            $table->dropIndex(['external_filing_id']);
            $table->dropColumn(['external_filing_id', 'revision_chain_id', 'publication_timestamp', 'official_source_available', 'official_pdf_url', 'official_xlsx_url', 'official_ixbrl_url']);
        });
    }
};
