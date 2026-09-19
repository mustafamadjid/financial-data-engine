<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class ValidationRuleSeeder extends Seeder
{
    public function run(): void
    {
        $codes = (array) config('financial-pipeline.validation.mandatory_rule_codes', []);
        $warnCodes = ['SCL-004', 'SCL-005', 'CRX-001'];
        $inputs = [
            'ACC-001' => ['total_assets', 'total_liabilities', 'temporary_syirkah_funds', 'total_equity', 'entry_point'],
            'HRY-001' => ['total_equity', 'equity_parent', 'non_controlling_interests'],
            'HRY-002' => ['total_assets', 'current_assets', 'non_current_assets', 'entry_point'],
            'HRY-003' => ['total_liabilities', 'current_liabilities', 'non_current_liabilities', 'entry_point'],
            'HRY-004' => ['gross_profit', 'revenue', 'cost_of_revenue', 'entry_point'],
            'HRY-005' => ['profit_loss', 'profit_loss_parent', 'profit_loss_nci'],
            'HRY-006' => ['net_change_cash', 'cash_flow_operating', 'cash_flow_investing', 'cash_flow_financing'],
            'CTX-001' => ['period_type', 'instant_date', 'start_date', 'end_date', 'period'],
            'CTX-002' => ['canonical_concept', 'period_type'],
            'CTX-003' => ['context_id', 'dimensions_json'],
            'CTX-004' => ['source_concept', 'context_id', 'unit_id', 'value_raw'],
            'SCL-001' => ['unit_id', 'currency', 'presentation_currency_dei'],
            'SCL-002' => ['canonical_concept', 'unit_id'],
            'SCL-003' => ['total_assets'],
            'SCL-004' => ['total_equity'],
            'SCL-005' => ['cash_and_cash_equivalents', 'ending_cash_cash_flow'],
            'CRX-001' => ['ticker', 'concept', 'comparative_value', 'prior_reported_value'],
            'CRX-002' => ['prior_end_year_q1', 'prior_end_year_q2'],
            'SCP-001' => ['dei_entity_scope', 'allowed_scope'],
            'SCP-002' => ['is_nil', 'value_raw'],
        ];

        foreach ($codes as $code) {
            DB::table('validation_rules')->updateOrInsert(
                ['rule_code' => $code, 'rule_version' => 1],
                [
                    'description' => "DA-3 validation rule {$code}.",
                    'severity' => in_array($code, $warnCodes, true) ? 'WARN' : 'ERROR',
                    'inputs' => json_encode($inputs[$code] ?? [], JSON_THROW_ON_ERROR),
                    'tolerance' => in_array($code, ['ACC-001', 'HRY-001', 'HRY-002', 'HRY-003', 'HRY-004', 'HRY-005', 'HRY-006'], true) ? 1000 : null,
                    'enabled' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }
}
