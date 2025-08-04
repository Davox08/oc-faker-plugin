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

    protected $description;

    public function __construct()
    {
        parent::__construct();
        $this->description = __('Generates fake data based on all configured standalone seeds.');
    }

    public function handle(): void
    {
        $this->info(__('Starting data generation process...'));

        $seeds = Seed::where('is_standalone', true)->get();

        if ($seeds->isEmpty()) {
            $this->warn(__('No standalone seeds configured. Aborting.'));

            return;
        }

        foreach ($seeds as $seed) {
            $this->line(__('Processing seed: :name...', ['name' => $seed->name]));

            if ($seed->record_count <= 0) {
                $this->warn(__('-> Skipping: Record count is zero or less.'));
                continue;
            }

            try {
                $this->generateDataForSeed($seed);
                $this->info(__('-> Successfully generated :count records for :model', [
                    'count' => $seed->record_count,
                    'model' => class_basename($seed->model_class),
                ]));
            } catch (Exception $ex) {
                $this->error(__('-> Error processing \':name\': :message', [
                    'name' => $seed->name,
                    'message' => $ex->getMessage(),
                ]));
                Log::error('Faker Console Error', ['message' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()]);
            }
        }

        $this->info(__('Data generation completed.'));
    }
}
