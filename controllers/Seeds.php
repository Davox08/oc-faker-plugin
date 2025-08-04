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
use Illuminate\Support\Str;

class Seeds extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';

    public $listConfig = 'config_list.yaml';

    protected static $fakerFormatters = null;

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Davox.Faker', 'faker', 'seeds');
    }

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

    public function onGenerateSingle()
    {
        try {
            $seedId = post('seed_id');
            $seed = Seed::findOrFail($seedId);
            $this->generateDataForSeed($seed);
            Flash::success(sprintf('Successfully generated %d records for %s.', $seed->record_count, class_basename($seed->model_class)));
        } catch (Exception $ex) {
            Flash::error($ex->getMessage());
            Log::error('Faker Plugin Error: ' . $ex->getMessage());
        }

        return $this->listRefresh();
    }

    public function onGenerateAll()
    {
        try {
            // Only fetch seeds that are marked as standalone
            $seeds = Seed::where('is_standalone', true)->get();
            if ($seeds->isEmpty()) {
                Flash::warning('No standalone seeds configured to generate data.');

                return;
            }

            foreach ($seeds as $seed) {
                $this->generateDataForSeed($seed);
            }

            Flash::success('Successfully generated data for all configured standalone seeds.');
        } catch (Exception $ex) {
            Flash::error($ex->getMessage());
            Log::error('Faker Plugin Error: ' . $ex->getMessage());
        }

        return $this->listRefresh();
    }

    public function onGenerateFromUpdateForm(): void
    {
        try {
            $model = $this->formGetModel();
            $this->generateDataForSeed($model);
            Flash::success(sprintf('Successfully generated %d records for %s.', $model->record_count, class_basename($model->model_class)));
        } catch (Exception $ex) {
            Flash::error($ex->getMessage());
            Log::error('Faker Plugin Error: ' . $ex->getMessage());
        }
    }

    public function formBeforeSave($model): void
    {
        $mappingsData = post('Seed')['mappings'] ?? [];
        $model->mappings = array_filter($mappingsData, fn($value) => ! empty($value));

        $relationsData = post('Seed')['relations'] ?? [];
        if (! is_array($relationsData)) {
            $relationsData = [];
        }
        $model->relations = array_filter($relationsData, fn($rel) => ! empty($rel['relationship_name']) && ! empty($rel['related_seed_id']));
    }

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

            $relationKeyExclusions = [];
            if (property_exists($model, 'belongsTo')) {
                foreach (array_keys($model->belongsTo) as $relationName) {
                    $relationKeyExclusions[] = Str::snake($relationName) . '_id';
                }
            }

            $guardedColumns = $model->getGuarded();
            $allExclusions = array_unique(array_merge($hardcodedExclusions, $guardedColumns, $relationKeyExclusions));

            return array_diff($allColumns, $allExclusions);
        } catch (Exception $e) {
            Log::error('Faker Plugin Error: Could not get columns for model. ' . $e->getMessage());

            return [];
        }
    }

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

    protected function generateDataForSeed(Seed $seed): void
    {
        $modelClass = $seed->model_class;
        $recordCount = $seed->record_count;
        $mappings = $seed->mappings ?? [];
        $relations = $seed->relations ?? [];

        if (! class_exists($modelClass)) {
            throw new Exception("Model class '{$modelClass}' not found for seed '{$seed->name}'.");
        }
        if ($recordCount <= 0) {
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
                continue;
            }

            $relationName = Str::camel($config['relationship_name']);
            $relationType = $config['relation_type'];
            $relatedSeedId = $config['related_seed_id'];
            $count = (int) ($config['record_count_per_parent'] ?? 1);

            $relatedSeed = Seed::find($relatedSeedId);
            if (! $relatedSeed) {
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
