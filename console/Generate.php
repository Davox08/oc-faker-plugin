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

        if (! class_exists($modelClass)) {
            throw new Exception("Model class '{$modelClass}' not found.");
        }

        if ($recordCount <= 0 || empty($mappings)) {
            $this->warn('-> Skipping: No records to generate or no fields have been mapped.');

            return;
        }

        $faker = Faker::create();

        Db::transaction(function () use ($modelClass, $recordCount, $mappings, $faker): void {
            for ($i = 0; $i < $recordCount; $i++) {
                $model = new $modelClass();
                foreach ($mappings as $column => $format) {
                    if (empty($format)) {
                        continue;
                    }

                    try {
                        $model->{$column} = $faker->{$format};
                    } catch (\InvalidArgumentException $e) {
                        throw new Exception("Invalid Faker format '{$format}' for column '{$column}'.");
                    }
                }
                $model->save();
            }
        });
    }
}
