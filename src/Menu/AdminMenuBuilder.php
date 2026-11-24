<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Menu;

use Knp\Menu\ItemInterface;
use OpenColibri\Starter\DevKitBundle\Settings\SettingsPages;
use Sylius\AdminUi\Knp\Menu\MenuBuilderInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

/**
 * Sidebar items of the dev-kit. An instance adds its own items by decorating "sylius_admin_ui.knp.menu_builder"
 * too: its decorator runs after this one and gets the items below.
 */
#[AsDecorator(decorates: 'sylius_admin_ui.knp.menu_builder')]
final class AdminMenuBuilder implements MenuBuilderInterface
{
    public function __construct(
        private readonly MenuBuilderInterface $menuBuilder,
        private readonly SettingsPages $pages,
    ) {}

    public function createMenu(array $options): ItemInterface
    {
        $menu = $this->menuBuilder->createMenu($options);

        $menu->addChild('dashboard', ['route' => 'sylius_admin_ui_dashboard'])
            ->setLabel('sylius.ui.dashboard')
            ->setLabelAttribute('icon', 'lucide:layout-dashboard')
        ;

        $menu->addChild('dev_kit', ['extras' => ['section' => true]])
            ->setLabel('opencolibri_dev_kit.ui.your_shop')
        ;

        foreach ($this->pages->all() as $page => $settings) {
            $menu->addChild($page, [
                'route' => 'opencolibri_dev_kit_settings',
                'routeParameters' => ['page' => $page],
                // The page of a dataset belongs to the catalog.
                'extras' => 'catalog' === $page ? ['routes' => [['pattern' => '/^opencolibri_dev_kit_dataset_/']]] : [],
            ])
                ->setLabel($settings['title'])
                ->setLabelAttribute('icon', $settings['icon'])
            ;
        }

        $menu->addChild('history', ['route' => 'opencolibri_dev_kit_history'])
            ->setLabel('opencolibri_dev_kit.ui.history')
            ->setLabelAttribute('icon', 'lucide:history')
        ;

        return $menu;
    }
}
