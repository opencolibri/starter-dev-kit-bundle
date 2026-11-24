<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use OpenColibri\Starter\DevKitBundle\Castor\CastorApiClient;
use OpenColibri\Starter\DevKitBundle\Import\ProjectConfigStorage;
use OpenColibri\Starter\DevKitBundle\Import\ShopImageStorage;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
    ;

    $services->load('OpenColibri\\Starter\\DevKitBundle\\', '../src/')
        ->exclude(['../src/OpenColibriStarterDevKitBundle.php'])
    ;

    $services->load('OpenColibri\\Starter\\DevKitBundle\\Controller\\', '../src/Controller/')
        ->tag('controller.service_arguments')
    ;

    $services->set(CastorApiClient::class)
        ->arg('$baseUrl', param('opencolibri_dev_kit.castor_api.url'))
        ->arg('$token', param('opencolibri_dev_kit.castor_api.token'))
    ;
};
