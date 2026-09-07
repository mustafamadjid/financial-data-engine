<?php

namespace App\Domain\FinancialData\Validation;

use App\Domain\FinancialData\Validation\Contracts\ValidationRuleProvider;
use App\Domain\FinancialData\Validation\Exceptions\TerminalValidationException;
use App\Models\ValidationRule as ValidationRuleModel;

final class DatabaseValidationRuleProvider implements ValidationRuleProvider
{
    public function __construct(private readonly ValidationRuleRegistry $registry) {}

    public function applicable(FilingValidationContext $context): iterable
    {
        $definitions = ValidationRuleModel::query()
            ->where('enabled', true)
            ->orderBy('rule_code')
            ->orderBy('rule_version')
            ->get();

        foreach ($definitions as $definitionModel) {
            $definition = ValidationRuleDefinition::fromModel($definitionModel);
            $implementation = $this->registry->resolve($definition->code);

            if ($implementation === null) {
                throw new TerminalValidationException("No validation implementation is registered for [{$definition->code}].");
            }

            if ($implementation->code() !== $definition->code || (int) $implementation->version() !== $definition->version) {
                throw new TerminalValidationException("Validation implementation [{$definition->code}] does not match its stored version.");
            }

            yield new ConfiguredValidationRule($implementation, $definition);
        }
    }
}
