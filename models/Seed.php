<?php

declare(strict_types=1);

namespace Davox\Faker\Models;

use File;
use System\Classes\PluginManager;
use System\Models\PluginVersion;
use System\Models\SettingModel;

/**
 * Seed Settings Model
 *
 * @link https://docs.octobercms.com/4.x/extend/settings/model-settings.html
 */
class Seed extends SettingModel
{
    /**
     * @var string settingsCode unique to this model
     */
    public $settingsCode = 'davox_faker_seed';

    /**
     * @var string settingsFields file
     */
    public $settingsFields = 'fields.yaml';

    /**
     * Returns a list of available plugins.
     *
     * @return array
     */
    public function getPluginCodeOptions()
    {
        // Corrected Approach: Fetch the full models to allow the 'afterFetch' event to fire,
        // which populates the 'name' attribute from the plugin details.
        $plugins = PluginVersion::applyEnabled()->get();

        // Now, we can pluck the name and code from the hydrated models.
        return $plugins->pluck('name', 'code')->all();
    }

    /**
     * Returns a list of available models for a given plugin.
     *
     * @return array
     */
    public function getModelClassOptions()
    {
        $options = [];
        $pluginCode = $this->plugin_code;

        if (empty($pluginCode)) {
            return $options;
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

        // Corrected: Construct the namespace directly from the plugin code.
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
