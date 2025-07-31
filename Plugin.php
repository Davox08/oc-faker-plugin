<?php

declare(strict_types=1);

namespace Davox\Faker;

use Backend;
use System\Classes\PluginBase;
use System\Classes\SettingsManager;

/**
 * Plugin Information File
 *
 * @link https://docs.octobercms.com/4.x/extend/system/plugins.html
 */
class Plugin extends PluginBase
{
    /**
     * pluginDetails about this plugin.
     */
    public function pluginDetails()
    {
        return [
            'name' => 'Faker',
            'description' => 'Provides tools for generating fake data for testing and development purposes.',
            'author' => 'Davox',
            'icon' => 'icon-magic',
        ];
    }

    /**
     * Registers any back-end permissions used by this plugin.
     *
     * @return array
     */
    public function registerPermissions()
    {
        return [
            'davox.faker.access_faker_seeds' => [
                'tab' => 'Faker',
                'label' => 'Access to faker seeds',
            ],
        ];
    }

    /**
     * registerSettings used by this plugin.
     */
    public function registerSettings()
    {
        return [
            'seeds' => [
                'label' => 'Faker',
                'description' => 'Generate fake data for your models.',
                'category' => SettingsManager::CATEGORY_MISC,
                'icon' => 'icon-magic',
                'url' => Backend::url('davox/faker/seeds'),
                'order' => 500,
                'keywords' => 'faker seed generator data',
                'permissions' => ['davox.faker.access_faker_seeds'],
            ],
        ];
    }
}
