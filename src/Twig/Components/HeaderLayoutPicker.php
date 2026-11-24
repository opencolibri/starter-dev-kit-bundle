<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Twig\Components;

use OpenColibri\Starter\DevKitBundle\Castor\CastorApiClient;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Where the storefront header shows the category menu and the search, picked on a wireframe of the header and
 * applied with a button. The placements are those of the active theme.
 */
#[AsLiveComponent]
final class HeaderLayoutPicker
{
    use DefaultActionTrait;
    use PlansChangeTrait;

    /** @var array{menus: list<string>, searches: list<string>}|null the placements of the active theme, null without any */
    #[LiveProp]
    public ?array $placements = null;

    #[LiveProp(writable: true)]
    public string $menu = '';

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
        $current = $state['theme']['name'] ?? null;

        foreach ($themes as $theme) {
            if (($theme['name'] ?? null) === $current) {
                $this->placements = ['menus' => (array) ($theme['menus'] ?? []), 'searches' => (array) ($theme['searches'] ?? [])];
            }
        }

        $this->menu = (string) ($this->getCurrent()['menu'] ?? '');
        $this->search = (string) ($this->getCurrent()['search'] ?? '');
    }

    /**
     * @return array{menu?: string, search?: string}
     */
    public function getCurrent(): array
    {
        $header = $this->state['theme']['header'] ?? [];

        return \is_array($header) ? $header : [];
    }

    protected function change(array $state): array
    {
        if (null === $this->placements || !\is_array($state['theme'] ?? null)) {
            return $state;
        }

        $state['theme']['header'] = array_filter(['menu' => $this->menu, 'search' => $this->search]) + $this->getCurrent();

        return $state;
    }
}
