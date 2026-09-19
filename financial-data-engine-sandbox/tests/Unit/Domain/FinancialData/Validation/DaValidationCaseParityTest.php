<?php

use App\Domain\FinancialData\Validation\ValidationRuleSetManifest;
use Tests\TestCase;

uses(TestCase::class);

it('covers every DA-1-3 validation case with a registered mandatory rule', function () {
    $path = base_path('../DA-1-3/DA-3/validation_cases.csv');
    expect(is_file($path))->toBeTrue();

    $handle = fopen($path, 'rb');
    $header = fgetcsv($handle);
    $codes = [];
    while (($row = fgetcsv($handle)) !== false) {
        if ($row === [] || $row === [null]) {
            continue;
        }
        $record = array_combine($header, $row);
        $codes[] = (string) ($record['rule_code'] ?? '');
    }
    fclose($handle);

    expect(array_values(array_unique(array_filter($codes))))
        ->toEqualCanonicalizing(ValidationRuleSetManifest::mandatoryCodes());
});
