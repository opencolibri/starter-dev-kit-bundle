<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * Pages of the dev-kit (history, thumbnails). Import them next to the Sylius Admin UI routes (login, logout, dashboard):
 *
 *     opencolibri_dev_kit:
 *         resource: '@OpenColibriStarterDevKitBundle/config/routes.php'
 */
return static function (RoutingConfigurator $routes): void {
    $routes->import('../src/Controller/', 'attribute');
};
