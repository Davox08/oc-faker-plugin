<?php

declare(strict_types=1);

namespace Davox\Faker\Controllers;

use Backend\Classes\Controller;
use Backend\Facades\BackendMenu;
use Davox\Faker\Models\Seed;
use Davox\Faker\Traits\FakerGenerator;
use Exception;
use Faker\Factory as Faker;
use Flash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Seeds extends Controller
{
    use FakerGenerator;

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
            if ($seed->record_count > 0) {
                $this->generateDataForSeed($seed);
                Flash::success(__('Successfully generated :count records for :model.', [
                    'count' => $seed->record_count,
                    'model' => class_basename($seed->model_class),
                ]));
            } else {
                Flash::info(__('Record count is zero. Nothing generated.'));
            }
        } catch (Exception $ex) {
            Flash::error($ex->getMessage());
            Log::error('Faker Plugin Error: ' . $ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }

        return $this->listRefresh();
    }

    public function onGenerateAll()
    {
        try {
            $seeds = Seed::where('is_standalone', true)->get();
            if ($seeds->isEmpty()) {
                Flash::warning(__('No standalone seeds configured to generate data.'));

                return;
            }

            $generatedCount = 0;
            foreach ($seeds as $seed) {
                if ($seed->record_count > 0) {
                    $this->generateDataForSeed($seed);
                    $generatedCount++;
                }
            }

            if ($generatedCount > 0) {
                Flash::success(__('Successfully generated data for all configured standalone seeds.'));
            } else {
                Flash::info(__('All standalone seeds have a record count of zero. Nothing generated.'));
            }
        } catch (Exception $ex) {
            Flash::error($ex->getMessage());
            Log::error('Faker Plugin Error: ' . $ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }

        return $this->listRefresh();
    }

    public function onGenerateFromUpdateForm(): void
    {
        try {
            $model = $this->formGetModel();
            if ($model->record_count > 0) {
                $this->generateDataForSeed($model);
                Flash::success(__('Successfully generated :count records for :model.', [
                    'count' => $model->record_count,
                    'model' => class_basename($model->model_class),
                ]));
            } else {
                Flash::info(__('Record count is zero. Nothing generated.'));
            }
        } catch (Exception $ex) {
            Flash::error($ex->getMessage());
            Log::error('Faker Plugin Error: ' . $ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
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
