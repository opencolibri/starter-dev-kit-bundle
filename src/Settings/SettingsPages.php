<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Settings;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The settings pages of the shop, in the sidebar and on /settings/{page}. Each page renders the hook
 * "opencolibri_dev_kit.<page>.index", whose body holds its cards.
 *
 * The bundle ships the storefront, catalog, locales, plugins and shop pages. An instance or a package adds its own
 * (custom checkout rules…) under opencolibri_dev_kit.pages, then fills "opencolibri_dev_kit.<page>.index.content.body".
 */
final readonly class SettingsPages
{
    public const array DEFAULTS = [
        'storefront' => ['title' => 'opencolibri_dev_kit.ui.storefront', 'subheader' => 'opencolibri_dev_kit.ui.storefront_subheader', 'icon' => 'lucide:paintbrush', 'priority' => 500],
        'catalog' => ['title' => 'opencolibri_dev_kit.ui.catalog', 'subheader' => 'opencolibri_dev_kit.ui.catalog_subheader', 'icon' => 'lucide:package', 'priority' => 400],
        'locales' => ['title' => 'opencolibri_dev_kit.ui.locales_and_currencies', 'subheader' => 'opencolibri_dev_kit.ui.locales_subheader', 'icon' => 'lucide:languages', 'priority' => 300],
        'plugins' => ['title' => 'opencolibri_dev_kit.ui.plugins', 'subheader' => 'opencolibri_dev_kit.ui.plugins_subheader', 'icon' => 'lucide:puzzle', 'priority' => 200],
        'shop' => ['title' => 'opencolibri_dev_kit.ui.shop', 'subheader' => 'opencolibri_dev_kit.ui.shop_subheader', 'icon' => 'lucide:store', 'priority' => 100],
    ];

    /**
     * @param array<string, array{title: string, subheader: string|null, icon: string, priority: int}> $pages
     */
    public function __construct(
        #[Autowire('%opencolibri_dev_kit.pages%')]
        private array $pages,
    ) {}

    /**
     * @return array<string, array{title: string, subheader: string|null, icon: string, priority: int}> by priority, highest first
     */
    public function all(): array
    {
        $pages = $this->pages;
        uasort($pages, static fn(array $a, array $b): int => $b['priority'] <=> $a['priority']);

        return $pages;
    }

    /**
     * @return array{title: string, subheader: string|null, icon: string, priority: int}|null
     */
    public function get(string $page): ?array
    {
        return $this->pages[$page] ?? null;
    }
}
