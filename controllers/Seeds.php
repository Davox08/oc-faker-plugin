<?php

declare(strict_types=1);

namespace Davox\Faker\Controllers;

use Backend\Classes\Controller;
use Backend\Facades\BackendMenu;
use Davox\Faker\Models\Seed;
use Db;
use Exception;
use Faker\Factory as Faker;
use Flash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds Back-end Controller
 */
class Seeds extends Controller
{
    /**
     * @var array Behaviors implemented by this controller.
     */
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    /**
     * @var string Configuration file for the form controller.
     */
    public $formConfig = 'config_form.yaml';

    /**
     * @var string Configuration file for the list controller.
     */
    public $listConfig = 'config_list.yaml';

    /**
     * @var array Cache for the Faker formatters list.
     */
    protected static $fakerFormatters = null;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Davox.Faker', 'faker', 'seeds');
    }

    /**
     * AJAX handler for refreshing the field mappings partial.
     * This is triggered when the user selects a model.
     */
    public function onRefreshFields()
    {
        $modelClass = post('Seed[model_class]');
        $columns = $this->getColumnsForModel($modelClass);

        return [
            '#fieldMappingsContainer' => $this->makePartial('field_mappings', [
                'columns' => $columns,
                'fakerFormatters' => $this->getFakerFormatters(),
            ]),
        ];
    }

    /**
     * AJAX handler for generating data for a single seed record from the list view.
     */
    public function onGenerateSingle()
    {
        try {
            $seedId = post('seed_id');
            $seed = Seed::findOrFail($seedId);
            $this->generateDataForSeed($seed);
            Flash::success(sprintf('Successfully generated %d records for %s.', $seed->record_count, class_basename($seed->model_class)));
        } catch (Exception $ex) {
            Flash::error($ex->getMessage());
        }

        return $this->listRefresh();
    }

    /**
     * AJAX handler for generating data for all seed records from the list toolbar.
     */
    public function onGenerateAll()
    {
        try {
            $seeds = Seed::all();
            if ($seeds->isEmpty()) {
                Flash::warning('No seeds configured to generate data.');

                return;
            }

            foreach ($seeds as $seed) {
                $this->generateDataForSeed($seed);
            }

            Flash::success('Successfully generated data for all configured seeds.');
        } catch (Exception $ex) {
            Flash::error($ex->getMessage());
        }

        return $this->listRefresh();
    }

    /**
     * AJAX handler for generating data for the current seed from the update form.
     */
    public function onGenerateFromUpdateForm(): void
    {
        try {
            // The model is already loaded in the form context.
            $model = $this->formGetModel();
            $this->generateDataForSeed($model);
            Flash::success(sprintf('Successfully generated %d records for %s.', $model->record_count, class_basename($model->model_class)));
        } catch (Exception $ex) {
            Flash::error($ex->getMessage());
        }
        // We don't return anything, so the page doesn't refresh. The flash message will appear.
    }

    /**
     * Called before the form model is saved.
     */
    public function formBeforeSave($model): void
    {
        $mappingsData = post('Seed')['mappings'] ?? [];
        $filteredMappings = array_filter($mappingsData, function ($value) {
            return ! empty($value);
        });
        $model->mappings = $filteredMappings;
    }

    /**
     * A helper function to get the filterable columns for a given model class.
     */
    protected function getColumnsForModel(?string $modelClass): array
    {
        if (! $modelClass || ! class_exists($modelClass)) {
            return [];
        }

        try {
            $model = new $modelClass();
            $table = $model->getTable();
            $allColumns = Schema::getColumnListing($table);

            $hardcodedExclusions = [
                'id', 'created_at', 'updated_at', 'deleted_at',
                'sort_order', 'nest_left', 'nest_right', 'nest_depth',
            ];

            $guardedColumns = $model->getGuarded();
            $allExclusions = array_unique(array_merge($hardcodedExclusions, $guardedColumns));

            return array_diff($allColumns, $allExclusions);
        } catch (Exception $e) {
            Log::error('Faker Plugin Error: Could not get columns for model. ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Inspects the Faker library to get a list of all available formatters.
     */
    protected function getFakerFormatters(): array
    {
        if (self::$fakerFormatters !== null) {
            return self::$fakerFormatters;
        }

        try {
            $faker = Faker::create();
            $formatters = [];
            $providers = $faker->getProviders();

            foreach ($providers as $provider) {
                $providerClass = new \ReflectionClass($provider);
                $methods = $providerClass->getMethods(\ReflectionMethod::IS_PUBLIC);

                foreach ($methods as $method) {
                    if ($method->isConstructor() || strpos($method->getName(), '__') === 0) {
                        continue;
                    }
                    $formatters[] = $method->getName();
                }
            }

            $formatters = array_unique($formatters);
            sort($formatters);

            return self::$fakerFormatters = array_combine($formatters, $formatters);
        } catch (\Throwable $t) {
            Log::error('Faker Plugin Error: Could not retrieve formatters. ' . $t->getMessage());

            return [];
        }
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
            throw new Exception("Model class '{$modelClass}' not found for seed '{$seed->name}'.");
        }

        if ($recordCount <= 0 || empty($mappings)) {
            Flash::warning("Skipping '{$seed->name}': No fields have been mapped to Faker providers.");

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

    /**
     * Override the form-specific methods to load necessary variables.
     */
    public function formExtendModel($model)
    {
        $this->vars['fakerFormatters'] = $this->getFakerFormatters();
        if ($model->model_class) {
            $this->vars['columns'] = $this->getColumnsForModel($model->model_class);
        }

        return $model;
    }

    public function create(): void
    {
        $this->asExtension('FormController')->create();
        $this->formExtendModel($this->formGetModel());
    }

    public function update($recordId = null): void
    {
        $this->asExtension('FormController')->update($recordId);
        $this->formExtendModel($this->formGetModel());
    }
}
