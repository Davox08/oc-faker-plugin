<?php

declare(strict_types=1);

namespace Davox\Faker\Traits;

use Davox\Faker\Models\Seed;
use Db;
use Exception;
use Faker\Factory as Faker;
use Illuminate\Support\Str;

trait FakerGenerator
{
    /**
     * Generates fake data for a given seed configuration.
     * This method is responsible for creating the parent model records.
     */
    protected function generateDataForSeed(Seed $seed): void
    {
        $modelClass = $seed->model_class;
        $recordCount = $seed->record_count;
        $mappings = $seed->mappings ?? [];
        $relations = $seed->relations ?? [];

        if (! class_exists($modelClass)) {
            throw new Exception(
                __('Model class \':modelClass\' not found for seed \':seedName\'.', [
                    'modelClass' => $modelClass,
                    'seedName' => $seed->name,
                ]),
            );
        }

        if ($recordCount <= 0) {
            // Silently return if no records need to be created.
            // The calling class can provide user feedback if necessary.
            return;
        }

        $faker = Faker::create();

        Db::transaction(function () use ($modelClass, $recordCount, $mappings, $relations, $faker, $seed): void {
            for ($i = 0; $i < $recordCount; $i++) {
                $parentModel = new $modelClass();
                foreach ($mappings as $column => $format) {
                    if (empty($format)) {
                        continue;
                    }

                    try {
                        $parentModel->{$column} = $faker->{$format};
                    } catch (\InvalidArgumentException $e) {
                        throw new Exception(
                            __('Invalid Faker format \':format\' for column \':column\' in seed \':seedName\'.', [
                                'format' => $format,
                                'column' => $column,
                                'seedName' => $seed->name,
                            ]),
                        );
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
     * Generates and associates related data based on the relations configuration.
     */
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
                // Silently skip if the related seed config is not found.
                // Could be logged in a more robust implementation.
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
