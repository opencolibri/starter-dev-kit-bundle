<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle;

use OpenColibri\Starter\DevKitBundle\Settings\SettingsPages;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * The dev-kit admin: builds the local Sylius shop little by little through the dev-kit:* tasks run by castor-api,
 * each change showing its plan and cost before it runs, in a Sylius Shadcn Admin UI panel.
 *
 * An instance is a small Symfony app next to the project that requires this bundle, imports "config/app.php" (hooks)
 * and the routes.
 */
final class OpenColibriStarterDevKitBundle extends AbstractBundle
{
    protected string $extensionAlias = 'opencolibri_dev_kit';

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('castor_api')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('url')->defaultValue('%env(CASTOR_API_URL)%')->info('Base URL of castor-api, which runs the import tasks.')->end()
                        ->scalarNode('token')->defaultValue('%env(default::CASTOR_API_TOKEN)%')->info('Bearer token of castor-api (empty: no auth).')->end()
                    ->end()
                ->end()
                ->arrayNode('pages')
                    ->info('Settings pages added to those of the bundle (storefront, catalog, locales, plugins, shop), or overriding them: fill their "opencolibri_dev_kit.<page>.index.content.body" hook.')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('title')->isRequired()->end()
                            ->scalarNode('subheader')->defaultNull()->end()
                            ->scalarNode('icon')->defaultValue('lucide:settings')->end()
                            ->integerNode('priority')->defaultValue(0)->info('Higher first in the sidebar.')->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('storefront_url')
                    ->defaultValue('%env(DEV_KIT_STOREFRONT_URL)%')
                    ->info('The storefront of the local shop, linked from the admin.')
                ->end()
                ->scalarNode('import_var_dir')
                    ->defaultValue('%env(IMPORT_VAR_DIR)%')
                    ->info('The .castor/import/var directory of the project, where dev-kit:apply reports its progress.')
                ->end()
            ->end()
        ;
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // <twig:DevKit:RunMonitor />, component('DevKit:StorefrontEditor')...
        $builder->prependExtensionConfig('twig_component', [
            // Required by TwigComponent, set by its Flex recipe: the instance may set its own.
            'anonymous_template_directory' => 'components/',
            'defaults' => [
                'OpenColibri\\Starter\\DevKitBundle\\Twig\\Components\\' => [
                    'template_directory' => '@OpenColibriStarterDevKit/components/',
                    'name_prefix' => 'DevKit',
                ],
            ],
        ]);

        // <twig:dev_kit:action_button>, <twig:dev_kit:message>: usable inside other components, unlike macros.
        $builder->prependExtensionConfig('sylius_twig_extra', [
            'twig_ux' => [
                'anonymous_component_template_prefixes' => [
                    'dev_kit' => '@OpenColibriStarterDevKit/components/ui',
                ],
            ],
        ]);
    }

    /**
     * @param array{castor_api: array{url: string, token: string}, pages: array<string, array{title: string, subheader: string|null, icon: string, priority: int}>, storefront_url: string, import_var_dir: string} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->parameters()
            ->set('opencolibri_dev_kit.castor_api.url', $config['castor_api']['url'])
            ->set('opencolibri_dev_kit.castor_api.token', $config['castor_api']['token'])
            ->set('opencolibri_dev_kit.pages', array_replace(SettingsPages::DEFAULTS, $config['pages']))
            ->set('opencolibri_dev_kit.storefront_url', $config['storefront_url'])
            ->set('opencolibri_dev_kit.import_var_dir', $config['import_var_dir'])
        ;

        $container->import('../config/services.php');
    }
}
