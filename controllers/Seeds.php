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
     * This method internally calls the 'update' action.
     */
    public function index(): void
    {
        // Call our update action with a symbolic ID.
        // This is required to prepare the FormController in the correct context.
        $this->update(1);
    }

    /**
     * Update action, handles the form logic.
     *
     * @param int|null $recordId The record ID to update.
     */
    public function update($recordId = null): void
    {
        $this->asExtension('FormController')->update($recordId);
        $this->pageTitle = __('Seeds');
        $this->pageSize = 750;

        // Now, check if a model is already selected and prepare the columns for the initial render.
        $model = $this->formGetModel();
        if ($model && ! empty($model->model_class)) {
            $this->vars['columns'] = $this->getColumnsForModel($model->model_class);
        }
    }

    /**
     * Finds the singleton model for the form.
     * This method is essential for the FormController to find the settings model.
     *
     * @param int $recordId The record ID.
     *
     * @return Seed
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
            '#fieldMappingsContainer' => $this->makePartial('field_mappings', ['columns' => $columns]),
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
}
