<?php

declare(strict_types=1);

namespace Davox\Faker\Console;

use Davox\Faker\Models\Seed;
use Db;
use Exception;
use Faker\Factory as Faker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Generate extends Command
{
    protected $name = 'faker:generate';

    protected $description = 'Generates fake data based on all configured seeds.';

    public function handle(): void
    {
        $this->info('Starting data generation process...');

        // Only fetch seeds that are marked as standalone
        $seeds = Seed::where('is_standalone', true)->get();

        if ($seeds->isEmpty()) {
            $this->warn('No standalone seeds configured. Aborting.');

            return;
        }

        foreach ($seeds as $seed) {
            $this->line("Processing seed: {$seed->name}...");

            try {
                $this->generateDataForSeed($seed);
                $this->info("-> Successfully generated {$seed->record_count} records for " . class_basename($seed->model_class));
            } catch (Exception $ex) {
                $this->error("-> Error processing '{$seed->name}': " . $ex->getMessage());
                Log::error('Faker Console Error', ['message' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()]);
            }
        }

        $this->info('Data generation completed.');
    }

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

    protected function generateRelatedData($parentModel, array $relations, $faker): void
    {
        foreach ($relations as $config) {
            if (empty($config['relationship_name']) || empty($config['related_seed_id'])) {
                $this->warn("--> Skipping relation due to incomplete configuration for model ID: {$parentModel->id}.");
                continue;
            }

            $relationName = Str::camel($config['relationship_name']);
            $relationType = $config['relation_type'];
            $relatedSeedId = $config['related_seed_id'];
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
                $modelsToAssociate[] = $relatedModel;
            }

            if (empty($modelsToAssociate)) {
                continue;
            }

            if ($relationType === 'add') {
                $parentModel->{$relationName}()->addMany($modelsToAssociate);
            } elseif ($relationType === 'attach') {
                foreach ($modelsToAssociate as $model) {
                    $model->save();
                }
                $ids = collect($modelsToAssociate)->pluck('id')->all();
                $parentModel->{$relationName}()->attach($ids);
            }
        }
    }
}
