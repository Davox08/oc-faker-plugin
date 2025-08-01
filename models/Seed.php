<?php

declare(strict_types=1);

namespace Davox\Faker\Models;

use File;
use Model;
use System\Classes\PluginManager;
use System\Models\PluginVersion;

/**
 * Seed Model
 */
class Seed extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string The table associated with the model.
     */
    public $table = 'davox_faker_seeds';

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['*'];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [
        'name',
        'plugin_code',
        'model_class',
        'record_count',
        'mappings',
    ];

    /**
     * @var array The attributes that should be cast.
     */
    protected $casts = [
        'mappings' => 'array',
        'record_count' => 'integer',
    ];

    /**
     * @var array Validation rules for attributes
     */
    public $rules = [
        'name' => 'required|string',
        'plugin_code' => 'required|string',
        'model_class' => 'required|string',
        'record_count' => 'required|integer|min:1',
    ];

    /**
     * Returns a list of available plugins.
     */
    public function getPluginCodeOptions()
    {
        $plugins = PluginVersion::applyEnabled()->get();

        return $plugins->pluck('name', 'code')->all();
    }

    /**
     * Returns a list of available models for a given plugin.
     */
    public function getModelClassOptions()
    {
        $options = [];
        $pluginCode = $this->plugin_code;

        if (empty($pluginCode)) {
            return ['' => '-- Select a plugin first --'];
        }

        $pluginManager = PluginManager::instance();
        $plugin = $pluginManager->findByIdentifier($pluginCode);

        if (! $plugin) {
            return $options;
        }

        $modelsPath = $pluginManager->getPluginPath($pluginCode) . '/models';

        if (! File::isDirectory($modelsPath)) {
            return $options;
        }

        $pluginNamespace = str_replace('.', '\\', $pluginCode);
        $files = File::files($modelsPath);

        foreach ($files as $file) {
            $className = $pluginNamespace . '\\Models\\' . $file->getFilenameWithoutExtension();
            if (class_exists($className)) {
                $options[$className] = $file->getFilenameWithoutExtension();
            }
        }

        return $options;
    }
}
