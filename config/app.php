<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

/*
 * Hooks of the dev-kit, imported by the instance (config/packages/opencolibri_dev_kit.yaml):
 *
 *     imports:
 *         - { resource: '@OpenColibriStarterDevKitBundle/config/app.php' }
 *
 * It imports the Sylius Shadcn Admin UI hooks first, so it is the only file to import. Hooks defined by the instance
 * after this import win: branding, extra hookables on a page, hookables disabled...
 *
 * Every page renders two hooks: its own ("opencolibri_dev_kit.history.index") and the common page layout
 * ("opencolibri_dev_kit.common.page": sidebar, navbar, flashes, header, run monitor). Child hooks are looked up
 * under both names, so a page only defines its title, actions and body.
 */
return static function (ContainerConfigurator $container): void {
    $container->import('@SyliusShadcnAdminUiBundle/config/app.php');

    $views = '@OpenColibriStarterDevKit';
    $shadcn = '@SyliusShadcnAdminUi/shared/crud/common';

    $hooks = [
        // Branding defaults: the instance may set its own name, subtitle and icon (or image).
        'sylius_admin.base#base_title' => [
            'default' => ['configuration' => ['title' => 'Dev-kit']],
        ],
        'sylius_admin.common.component.sidebar.logo' => [
            'image' => ['configuration' => ['name' => 'Dev-kit', 'subtitle' => 'Colibri Starter', 'icon' => 'lucide:hammer']],
        ],
        'sylius_admin.security.login.page.logo' => [
            'image' => ['configuration' => ['name' => 'Dev-kit', 'icon' => 'lucide:hammer']],
        ],

        // Dashboard: what the shop runs now, one card per settings page, and the runs in progress.
        'sylius_admin.dashboard.index.content.header.title_block' => [
            'title' => ['configuration' => ['subheader' => 'opencolibri_dev_kit.ui.dashboard_subheader']],
            'actions' => [
                'enabled' => true,
                'template' => $views . '/dashboard/actions.html.twig',
            ],
        ],
        'sylius_admin.dashboard.index.content' => [
            'opencolibri_dev_kit_unavailable' => ['template' => $views . '/shared/unavailable.html.twig', 'priority' => -5],
            'opencolibri_dev_kit_summary' => ['template' => $views . '/dashboard/summary.html.twig', 'priority' => -10],
            'opencolibri_dev_kit_run_monitor' => [
                'template' => $views . '/shared/run_monitor.html.twig',
                'priority' => -100,
                'configuration' => ['reload_route' => 'sylius_admin_ui_dashboard'],
            ],
        ],

        // Common page layout.
        'opencolibri_dev_kit.common.page' => [
            'sidebar' => ['template' => $shadcn . '/sidebar.html.twig'],
            'navbar' => ['template' => $shadcn . '/navbar.html.twig'],
            'content' => ['template' => $shadcn . '/content.html.twig'],
        ],
        'opencolibri_dev_kit.common.page.content' => [
            'header' => ['template' => $shadcn . '/content/header.html.twig', 'priority' => 200],
            'flashes' => ['template' => $shadcn . '/content/flashes.html.twig', 'priority' => 100],
            'body' => ['template' => $views . '/shared/body.html.twig', 'priority' => 0],
            'run_monitor' => ['template' => $views . '/shared/run_monitor.html.twig', 'priority' => -100],
        ],
        'opencolibri_dev_kit.common.page.content.header' => [
            'breadcrumbs' => ['template' => $views . '/shared/breadcrumbs.html.twig', 'priority' => 100],
            'title_block' => ['template' => $shadcn . '/content/header/title_block.html.twig', 'priority' => 0],
        ],
        'opencolibri_dev_kit.common.page.content.header.title_block' => [
            'title' => ['template' => $views . '/shared/title.html.twig', 'priority' => 100],
        ],

        // Settings pages: the cards of their settings, each showing its plan before it runs.
        'opencolibri_dev_kit.storefront.index.content.body' => [
            'unavailable' => ['template' => $views . '/shared/unavailable.html.twig', 'priority' => 100],
            'settings' => ['template' => $views . '/settings/storefront.html.twig', 'priority' => 0],
        ],
        'opencolibri_dev_kit.catalog.index.content.body' => [
            'unavailable' => ['template' => $views . '/shared/unavailable.html.twig', 'priority' => 100],
            'settings' => ['template' => $views . '/settings/catalog.html.twig', 'priority' => 0],
        ],
        'opencolibri_dev_kit.locales.index.content.body' => [
            'unavailable' => ['template' => $views . '/shared/unavailable.html.twig', 'priority' => 100],
            'settings' => ['template' => $views . '/settings/locales.html.twig', 'priority' => 0],
        ],
        'opencolibri_dev_kit.plugins.index.content.body' => [
            'unavailable' => ['template' => $views . '/shared/unavailable.html.twig', 'priority' => 100],
            'settings' => ['template' => $views . '/settings/plugins.html.twig', 'priority' => 0],
        ],
        'opencolibri_dev_kit.shop.index.content.body' => [
            'unavailable' => ['template' => $views . '/shared/unavailable.html.twig', 'priority' => 100],
            'settings' => ['template' => $views . '/settings/shop.html.twig', 'priority' => 0],
        ],

        // A dataset, before it is loaded: its products, what it is and how to load it.
        'opencolibri_dev_kit.dataset.show.content.body' => [
            'load' => ['template' => $views . '/dataset/show/load.html.twig', 'priority' => 100],
            'columns' => ['template' => $views . '/dataset/show/columns.html.twig', 'priority' => 0],
        ],
        'opencolibri_dev_kit.dataset.show.content.body.main' => [
            'catalog' => ['template' => $views . '/dataset/show/catalog.html.twig'],
        ],
        'opencolibri_dev_kit.dataset.show.content.body.aside' => [
            'about' => ['template' => $views . '/dataset/show/about.html.twig'],
        ],

        // History
        'opencolibri_dev_kit.history.index.content.body' => [
            'list' => ['template' => $views . '/history/list.html.twig'],
        ],
    ];

    $container->extension('sylius_twig_hooks', ['hooks' => $hooks]);
};
