<?php

declare(strict_types=1);

namespace Davox\Faker\Console;

use Davox\Faker\Models\Seed;
use Db;
use Exception;
use Faker\Factory as Faker;
use Illuminate\Console\Command;

/**
 * Generate Command
 *
 * This command generates fake data based on all configured seeds in the database.
 */
class Generate extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'faker:generate';

    /**
     * @var string The console command description.
     */
    protected $description = 'Generates fake data based on all configured seeds.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Starting data generation process...');

        $seeds = Seed::all();

        if ($seeds->isEmpty()) {
            $this->warn('No seeds configured. Aborting.');

            return;
        }

        foreach ($seeds as $seed) {
            $this->line("Processing seed: {$seed->name}...");

            try {
                $this->generateDataForSeed($seed);
                $this->info("-> Successfully generated {$seed->record_count} records for " . class_basename($seed->model_class));
            } catch (Exception $ex) {
                $this->error("-> Error processing '{$seed->name}': " . $ex->getMessage());
            }
        }

        $this->info('Data generation completed.');
    }

    /**
     * Core data generation logic for a given seed configuration.
     */
    protected function generateDataForSeed(Seed $seed): void
    {
        $modelClass = $seed->model_class;
        $recordCount = $seed->record_count;
        $mappings = $seed->mappings ?? [];
        $relations = $seed->relations ?? [];

        if (! class_exists($modelClass)) {
            throw new Exception("Model class '{$modelClass}' not found.");
        }

        if ($recordCount <= 0) {
            $this->warn('-> Skipping: Record count is zero or less.');

            return;
        }

        $faker = Faker::create();

        Db::transaction(function () use ($modelClass, $recordCount, $mappings, $relations, $faker): void {
            for ($i = 0; $i < $recordCount; $i++) {
                $parentModel = new $modelClass();
                foreach ($mappings as $column => $format) {
                    if (empty($format)) {
                        continue;
                    }

                    try {
                        $parentModel->{$column} = $faker->{$format};
                    } catch (\InvalidArgumentException $e) {
                        throw new Exception("Invalid Faker format '{$format}' for column '{$column}'.");
                    }
                }
                $parentModel->save();

                if (! empty($relations)) {
                    $this->generateRelatedData($parentModel, $relations, $faker);
                }
            }
        });
    }

    /**
     * Generates and associates related models based on the configuration.
     */
    protected function generateRelatedData($parentModel, array $relations, $faker): void
    {
        foreach ($relations as $config) {
            $relationName = $config['relationship_name'] ?? null;

            if (empty($relationName)) {
                $this->warn("--> Skipping relation due to empty relationship name in configuration for model ID: {$parentModel->id}.");
                continue;
            }

            $relatedSeedId = $config['related_seed_id'];
            $relationType = $config['relation_type'];
            $count = (int) ($config['record_count_per_parent'] ?? 1);

            $relatedSeed = Seed::find($relatedSeedId);
            if (! $relatedSeed) {
                $this->warn("--> Skipping relation '{$relationName}': Related seed ID #{$relatedSeedId} not found.");
                continue;
            }

            $relatedModelClass = $relatedSeed->model_class;
            $relatedMappings = $relatedSeed->mappings ?? [];

            $modelsToAssociate = [];
            for ($i = 0; $i < $count; $i++) {
                $relatedModel = new $relatedModelClass();
                foreach ($relatedMappings as $column => $format) {
                    $relatedModel->{$column} = $faker->{$format};
                }
                $relatedModel->save();
                $modelsToAssociate[] = $relatedModel;
            }

            if (empty($modelsToAssociate)) {
                continue;
            }

            if ($relationType === 'add') {
                $parentModel->{$relationName}()->addMany($modelsToAssociate);
            } elseif ($relationType === 'attach') {
                $ids = collect($modelsToAssociate)->pluck('id')->all();
                $parentModel->{$relationName}()->attach($ids);
            }
        }
    }
}
