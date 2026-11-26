<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Twig\Components;

use OpenColibri\Starter\DevKitBundle\Castor\CastorApiClient;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The storefront: its theme, picked on its thumbnails grouped and filtered by the categories of the documentation, and
 * where its header shows the category menu and the search, picked in the sidebar for the picked theme. Both apply
 * together, in one plan. Setting a theme up builds the assets: the storefront is down a few seconds. The header layout
 * is kept when the new theme supports it, the new theme's own layout is used otherwise.
 */
#[AsLiveComponent]
final class StorefrontEditor
{
    use DefaultActionTrait;
    use PlansChangeTrait;

    /** The category of the themes that have none in dev-kit:catalog: custom themes, or an older catalog. */
    private const string OTHER = 'other';

    /** @var list<array<string, mixed>> the themes of dev-kit:catalog */
    #[LiveProp]
    public array $themes = [];

    #[LiveProp(writable: true)]
    public string $theme = '';

    /** The category shown, or '' for every theme. */
    #[LiveProp(writable: true)]
    public string $category = '';

    /** The wanted menu placement, used when the picked theme supports it. */
    #[LiveProp(writable: true)]
    public string $menu = '';

    /** The wanted search placement, used when the picked theme supports it. */
    #[LiveProp(writable: true)]
    public string $search = '';

    public function __construct(
        private readonly CastorApiClient $castorApi,
    ) {}

    /**
     * @param array<string, mixed>       $state
     * @param list<array<string, mixed>> $themes
     */
    public function mount(array $state, array $themes): void
    {
        $this->state = $state;
        $this->themes = $themes;
        $this->discard();
    }

    /**
     * Back to what the shop runs now. The category filter stays.
     */
    #[LiveAction]
    public function discard(): void
    {
        $this->theme = self::themeOf($this->state) ?? '';
        $this->menu = (string) ($this->getCurrentHeader()['menu'] ?? '');
        $this->search = (string) ($this->getCurrentHeader()['search'] ?? '');
        $this->error = null;
    }

    public function getCurrent(): ?string
    {
        return self::themeOf($this->state);
    }

    /**
     * @return array{menu?: string, search?: string}
     */
    public function getCurrentHeader(): array
    {
        $header = $this->state['theme']['header'] ?? [];

        return \is_array($header) ? $header : [];
    }

    /**
     * The picked theme, as dev-kit:catalog lists it, or null for the Sylius look (or none).
     *
     * @return array<string, mixed>|null
     */
    public function getPicked(): ?array
    {
        foreach ($this->themes as $theme) {
            if (($theme['name'] ?? null) === $this->theme) {
                return $theme;
            }
        }

        return null;
    }

    /**
     * The header placements the picked theme supports, null for the Sylius look.
     *
     * @return array{menus: list<string>, searches: list<string>}|null
     */
    public function getPlacements(): ?array
    {
        $theme = $this->getPicked();

        if (null === $theme) {
            return null;
        }

        return ['menus' => array_values((array) ($theme['menus'] ?? [])), 'searches' => array_values((array) ($theme['searches'] ?? []))];
    }

    /**
     * The header the picked theme gets: the wanted placements it supports, its own ones otherwise.
     *
     * @return array{menu: string, search: string}|null null for the Sylius look
     */
    public function getHeader(): ?array
    {
        $theme = $this->getPicked();
        $placements = $this->getPlacements();

        if (null === $theme || null === $placements) {
            return null;
        }

        return [
            'menu' => \in_array($this->menu, $placements['menus'], true) ? $this->menu : (string) ($theme['menu'] ?? ''),
            'search' => \in_array($this->search, $placements['searches'], true) ? $this->search : (string) ($theme['search'] ?? ''),
        ];
    }

    /**
     * The categories of the themes, in the order of dev-kit:catalog, with the number of their themes. The themes of no
     * category (custom ones) are under "other", last.
     *
     * @return list<array{name: string, label: ?string, description: ?string, count: int}>
     */
    public function getCategories(): array
    {
        $categories = [];

        foreach ($this->themes as $theme) {
            $category = self::categoryOf($theme);
            $categories[$category['name']] ??= $category + ['count' => 0];
            ++$categories[$category['name']]['count'];
        }

        if (isset($categories[self::OTHER])) {
            $other = $categories[self::OTHER];
            unset($categories[self::OTHER]);
            $categories[self::OTHER] = $other;
        }

        return array_values($categories);
    }

    /**
     * The themes shown, by category: those of the picked one, or all of them.
     *
     * @return list<array{name: string, label: ?string, description: ?string, themes: list<array<string, mixed>>}>
     */
    public function getGroups(): array
    {
        $categories = $this->getCategories();
        // An unknown category (gone from the catalog) shows every theme.
        $picked = \in_array($this->category, array_column($categories, 'name'), true) ? $this->category : '';
        $groups = [];

        foreach ($categories as $category) {
            if ('' !== $picked && $category['name'] !== $picked) {
                continue;
            }

            unset($category['count']);
            $groups[] = $category + ['themes' => array_values(array_filter(
                $this->themes,
                static fn(array $theme): bool => self::categoryOf($theme)['name'] === $category['name'],
            ))];
        }

        return $groups;
    }

    protected function change(array $state): array
    {
        if ('' === $this->theme) {
            return $state;
        }

        if ('default' === $this->theme) {
            if ('default' !== self::themeOf($state)) {
                $state['theme'] = ['name' => 'default'];
            }

            return $state;
        }

        // The same theme: the placements picked in the sidebar, over the current ones.
        if ($this->theme === self::themeOf($state)) {
            if (null !== $this->getPlacements()) {
                $state['theme']['header'] = array_filter(['menu' => $this->menu, 'search' => $this->search]) + $this->getCurrentHeader();
            }

            return $state;
        }

        // A new theme: the wanted placements it supports, the others left to its setup.
        $placements = $this->getPlacements() ?? ['menus' => [], 'searches' => []];
        $kept = array_filter([
            'menu' => \in_array($this->menu, $placements['menus'], true) ? $this->menu : null,
            'search' => \in_array($this->search, $placements['searches'], true) ? $this->search : null,
        ]);
        $state['theme'] = ['name' => $this->theme] + ([] !== $kept ? ['header' => $kept] : []);

        return $state;
    }

    /**
     * @param array<string, mixed> $theme
     *
     * @return array{name: string, label: ?string, description: ?string}
     */
    private static function categoryOf(array $theme): array
    {
        $category = $theme['category'] ?? null;

        if (!\is_array($category) || !\is_string($category['name'] ?? null) || '' === $category['name']) {
            return ['name' => self::OTHER, 'label' => null, 'description' => null];
        }

        return [
            'name' => $category['name'],
            'label' => \is_string($category['label'] ?? null) ? $category['label'] : null,
            'description' => \is_string($category['description'] ?? null) ? $category['description'] : null,
        ];
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function themeOf(array $state): ?string
    {
        $name = $state['theme']['name'] ?? null;

        return \is_string($name) ? $name : null;
    }
}
