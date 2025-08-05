<?php

declare(strict_types=1);

namespace Davox\Faker;

use Backend;
use System\Classes\PluginBase;

/**
 * Plugin Information File
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
     * Registers any console commands implemented in your plugin.
     */
    public function register(): void
    {
        $this->registerConsoleCommand('faker.generate', Console\Generate::class);
    }

    /**
     * Registers back-end navigation items for this plugin.
     */
    public function registerNavigation()
    {
        return [
            'faker' => [
                'label' => 'Faker',
                'url' => Backend::url('davox/faker/seeds'),
                'icon' => 'icon-magic',
                'permissions' => ['davox.faker.*'],
                'order' => 500,

                'sideMenu' => [
                    'seeds' => [
                        'label' => 'Seeds',
                        'icon' => 'icon-cogs',
                        'url' => Backend::url('davox/faker/seeds'),
                        'permissions' => ['davox.faker.access_seeds'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Registers any back-end permissions used by this plugin.
     */
    public function registerPermissions()
    {
        return [
            'davox.faker.access_seeds' => [
                'tab' => 'Faker',
                'label' => 'Access and manage Faker seed configurations',
            ],
        ];
    }
}
