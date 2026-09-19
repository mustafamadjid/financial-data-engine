<?php

namespace App\Domain\FinancialData\Validation;

use App\Domain\FinancialData\Validation\Exceptions\TerminalValidationException;

final class ValidationRuleSetManifest
{
    /** @return list<string> */
    public static function mandatoryCodes(): array
    {
        return array_values(array_map(
            static fn (mixed $code): string => (string) $code,
            (array) config('financial-pipeline.validation.mandatory_rule_codes', []),
        ));
    }

    /** @param iterable<string> $enabledCodes */
    public static function assertComplete(iterable $enabledCodes): void
    {
        $enabled = [];
        foreach ($enabledCodes as $code) {
            $enabled[(string) $code] = true;
        }

        $missing = array_values(array_filter(
            self::mandatoryCodes(),
            static fn (string $code): bool => ! isset($enabled[$code]),
        ));

        if ($missing !== []) {
            throw new TerminalValidationException(
                'The configured DA validation rule set is incomplete: '.implode(', ', $missing).'.',
            );
        }
    }
}
