<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Twig\Components;

use OpenColibri\Starter\DevKitBundle\Castor\CastorApiClient;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The storefront theme, picked on its thumbnails. Setting it up builds the assets: the storefront is down a few
 * seconds, so the plan shows before anything runs. The header layout is kept when the new theme supports it.
 */
#[AsLiveComponent]
final class ThemePicker
{
    use DefaultActionTrait;
    use PlansChangeTrait;

    /** @var list<array<string, mixed>> the themes of dev-kit:catalog */
    #[LiveProp]
    public array $themes = [];

    #[LiveProp(writable: true)]
    public string $theme = '';

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
        $this->theme = self::themeOf($state) ?? '';
    }

    public function getCurrent(): ?string
    {
        return self::themeOf($this->state);
    }

    protected function change(array $state): array
    {
        if ('' === $this->theme || $this->theme === self::themeOf($state)) {
            return $state;
        }

        $header = \is_array($state['theme']['header'] ?? null) ? $state['theme']['header'] : [];
        $theme = $this->catalogTheme($this->theme);
        $kept = array_filter([
            'menu' => \in_array($header['menu'] ?? null, $theme['menus'] ?? [], true) ? $header['menu'] : null,
            'search' => \in_array($header['search'] ?? null, $theme['searches'] ?? [], true) ? $header['search'] : null,
        ]);
        $state['theme'] = ['name' => $this->theme] + ('default' !== $this->theme && [] !== $kept ? ['header' => $kept] : []);

        return $state;
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogTheme(string $name): array
    {
        foreach ($this->themes as $theme) {
            if (($theme['name'] ?? null) === $name) {
                return $theme;
            }
        }

        return [];
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
