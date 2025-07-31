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
use System\Classes\SettingsManager;
use ValidationException;

/**
 * Seeds Back-end Controller
 */
class Seeds extends Controller
{
    /**
     * @var array implemented behaviors
     */
    public $implement = [
        \Backend\Behaviors\FormController::class,
    ];

    /**
     * @var string Configuration file for the form controller.
     */
    public $formConfig = 'config_form.yaml';

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

        BackendMenu::setContext('October.System', 'system', 'settings');
        SettingsManager::setContext('Davox.Faker', 'seeds');
        $this->pageTitle = 'Faker Seeds';
    }

    /**
     * The default action, displays the settings update form.
     */
    public function index(): void
    {
        $this->update(1);
    }

    /**
     * Update action, handles the form logic and prepares data for the initial view.
     */
    public function update($recordId = null): void
    {
        $this->asExtension('FormController')->update($recordId);
        $this->pageTitle = __('Seeds');
        $this->pageSize = 750;

        $this->vars['fakerFormatters'] = $this->getFakerFormatters();

        $model = $this->formGetModel();
        if ($model && ! empty($model->model_class)) {
            $this->vars['columns'] = $this->getColumnsForModel($model->model_class);
        }
    }

    /**
     * Finds the singleton model for the form.
     */
    public function formFindModelObject($recordId)
    {
        return Seed::instance();
    }

    /**
     * Handles the save action from the settings form.
     */
    public function onSave(): void
    {
        $this->asExtension('FormController')->update_onSave(1);
        Flash::success(__('Settings successfully saved.'));
    }

    /**
     * AJAX handler for refreshing the field mappings partial.
     */
    public function onRefreshFields()
    {
        $modelClass = post('Seed')['model_class'] ?? null;
        $columns = $this->getColumnsForModel($modelClass);

        return [
            '#fieldMappingsContainer' => $this->makePartial('field_mappings', [
                'columns' => $columns,
                'fakerFormatters' => $this->getFakerFormatters(),
            ]),
        ];
    }

    /**
     * AJAX handler for generating the fake data.
     */
    public function onGenerateData(): void
    {
        try {
            $data = post();
            $settings = Seed::instance();

            $modelClass = $settings->model_class;
            $recordCount = $settings->record_count;
            $mappings = $data['mappings'] ?? [];

            if (! class_exists($modelClass)) {
                throw new ValidationException(['model_class' => 'Please select a valid model.']);
            }

            if ($recordCount <= 0) {
                throw new ValidationException(['record_count' => 'Number of records must be greater than zero.']);
            }

            if (empty($mappings)) {
                throw new Exception('Please provide at least one field mapping.');
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
                            throw new Exception("Invalid Faker format requested: '{$format}'. Please check the Faker documentation for available formatters.");
                        }
                    }
                    $model->save();
                }
            });

            Flash::success(sprintf('Successfully generated %d records for %s.', $recordCount, class_basename($modelClass)));
        } catch (Exception $ex) {
            Flash::error($ex->getMessage());
        }
    }

    /**
     * A helper function to get the filterable columns for a given model class.
     */
    protected function getColumnsForModel(?string $modelClass): array
    {
        if (! $modelClass || ! class_exists($modelClass)) {
            return [];
        }

        $model = new $modelClass();
        $table = $model->getTable();
        $allColumns = Schema::getColumnListing($table);

        return array_diff($allColumns, [
            'id', 'created_at', 'updated_at', 'deleted_at',
            'sort_order', 'nest_left', 'nest_right', 'nest_depth',
        ]);
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
            // Log the error silently and return an empty array to prevent crashing the UI.
            Log::error('Faker Plugin Error: Could not retrieve formatters. ' . $t->getMessage());

            return [];
        }
    }
}
