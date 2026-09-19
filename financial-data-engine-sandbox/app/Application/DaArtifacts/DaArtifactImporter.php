<?php

namespace App\Application\DaArtifacts;

use App\Models\CanonicalConcept;
use App\Models\ConceptMapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class DaArtifactImporter
{
    /**
     * @return array{canonical_count:int,mapping_count:int,artifact_sha256:string}
     */
    public function import(string $basePath, string $version, bool $dryRun = false): array
    {
        $dictionaryPath = rtrim($basePath, '\\/').DIRECTORY_SEPARATOR.'DA-2'.DIRECTORY_SEPARATOR.'canonical_financial_dictionary.csv';
        $mappingPath = rtrim($basePath, '\\/').DIRECTORY_SEPARATOR.'DA-2'.DIRECTORY_SEPARATOR.'concept_mapping.csv';
        $dictionary = $this->readCsv($dictionaryPath);
        $mappings = $this->readCsv($mappingPath);
        $this->validate($dictionary, $mappings);
        $hash = hash('sha256', hash_file('sha256', $dictionaryPath).hash_file('sha256', $mappingPath));

        $previous = Schema::hasTable('da_artifact_imports') && DB::table('da_artifact_imports')
            ->where('artifact_type', 'DA-2')
            ->where('artifact_version', $version)
            ->where('source_sha256', '!=', $hash)
            ->exists();
        if ($previous) {
            throw new RuntimeException("DA artifact version {$version} already exists with a different SHA-256; publish a new version.");
        }

        if (! $dryRun) {
            DB::transaction(function () use ($dictionary, $mappings, $version, $basePath, $hash): void {
                foreach ($dictionary as $row) {
                    CanonicalConcept::query()->updateOrCreate(
                        ['code' => $row['canonical_concept']],
                        [
                            'name' => $row['canonical_concept'],
                            'statement' => $row['statement'],
                            'period_type' => strtoupper($row['period_type']),
                            'sign_convention' => 'AS_REPORTED',
                            'allowed_scope' => array_values(array_filter(explode(';', $row['allowed_scope']))),
                            'required_status' => $row['required'],
                            'formula_dependency' => $row['formula_dependency'] === '' ? [] : [$row['formula_dependency']],
                            'description' => $row['definition'],
                        ],
                    );
                }
                foreach ($mappings as $row) {
                    ConceptMapping::query()->updateOrCreate(
                        ['mapping_rule_id' => 'da-'.$version.'-'.substr(hash('sha256', implode('|', [$row['source_namespace'], $row['source_concept'], $row['canonical_concept']])), 0, 32)],
                        [
                            'source_concept' => $row['source_concept'],
                            'source_namespace' => $row['concept_namespace'],
                            'entry_point' => $row['entry_point'],
                            'canonical_concept' => $row['canonical_concept'],
                            'status' => $this->mappingStatus($row['status']),
                            'period_type' => null,
                            'dimension_policy' => 'UNDIMENSIONED_ONLY',
                            'sign_convention' => 'AS_REPORTED',
                            'rationale' => $row['rationale'],
                            'reviewer' => $row['reviewer'],
                            'rule_version' => $this->versionNumber($version),
                        ],
                    );
                }
                DB::table('da_artifact_imports')->updateOrInsert(
                    ['artifact_type' => 'DA-2', 'artifact_version' => $version, 'source_sha256' => $hash],
                    ['source_path' => $basePath, 'row_count' => count($dictionary) + count($mappings), 'status' => 'IMPORTED', 'actor_id' => 'system', 'updated_at' => now(), 'created_at' => now()],
                );
            });
        }

        return ['canonical_count' => count($dictionary), 'mapping_count' => count($mappings), 'artifact_sha256' => $hash];
    }

    /** @return list<array<string,string>> */
    private function readCsv(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("DA artifact not found: {$path}");
        }
        $handle = fopen($path, 'rb');
        $header = fgetcsv($handle);
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || $row === [] || count(array_filter($row, fn ($value): bool => trim((string) $value) !== '')) === 0) {
                continue;
            }
            if (count($row) !== count($header)) {
                throw new RuntimeException("Invalid CSV column count in {$path}");
            }
            $rows[] = array_combine($header, $row);
        }
        fclose($handle);

        return $rows;
    }

    /** @param list<array<string,string>> $dictionary @param list<array<string,string>> $mappings */
    private function validate(array $dictionary, array $mappings): void
    {
        $canonical = array_fill_keys(array_column($dictionary, 'canonical_concept'), true);
        foreach ($dictionary as $row) {
            if (! in_array(trim($row['period_type']), ['instant', 'duration'], true) || trim($row['sign_convention']) !== 'source sign' || ! in_array(trim($row['required']), ['required', 'optional'], true)) {
                throw new RuntimeException('DA dictionary contains an invalid semantic row.');
            }
        }
        foreach ($mappings as $row) {
            if (! isset($canonical[$row['canonical_concept']]) || trim($row['concept_namespace']) === '') {
                throw new RuntimeException('DA mapping contains an unknown concept or missing namespace.');
            }
        }
    }

    private function versionNumber(string $version): int
    {
        return preg_match('/(\d+)$/', trim($version), $matches) === 1 ? (int) $matches[1] : 1;
    }

    private function mappingStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'PROPOSED' => 'DRAFT',
            'CONFIRMED' => 'APPROVED',
            default => strtoupper($status),
        };
    }
}
