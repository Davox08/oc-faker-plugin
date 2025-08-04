<?php

declare(strict_types=1);

namespace Davox\Faker\Console;

use Davox\Faker\Models\Seed;
use Davox\Faker\Traits\FakerGenerator;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class Generate extends Command
{
    use FakerGenerator;

    protected $name = 'faker:generate';

    protected $description = 'Generates fake data based on all configured standalone seeds.';

    public function handle(): void
    {
        $this->info('Starting data generation process...');

        $seeds = Seed::where('is_standalone', true)->get();

        if ($seeds->isEmpty()) {
            $this->warn('No standalone seeds configured. Aborting.');

            return;
        }

        foreach ($seeds as $seed) {
            $this->line("Processing seed: {$seed->name}...");

            if ($seed->record_count <= 0) {
                $this->warn('-> Skipping: Record count is zero or less.');
                continue;
            }

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
}
