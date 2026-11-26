<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Twig\Components;

use OpenColibri\Starter\DevKitBundle\Castor\CastorApiClient;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The plugins of opencolibri/starter-plugins, searched as you type. Checked ones are installed together, in one run
 * of Composer, migrations and asset build; unchecked installed ones are removed, when they have a remover. The
 * sidebar lists what changes and applies it.
 */
#[AsLiveComponent]
final class PluginsPicker
{
    use DefaultActionTrait;
    use PlansChangeTrait;

    /** @var list<array<string, mixed>> the plugins of dev-kit:catalog: name, description, removable */
    #[LiveProp]
    public array $plugins = [];

    /** @var list<string> */
    #[LiveProp(writable: true)]
    public array $selected = [];

    #[LiveProp(writable: true)]
    public string $query = '';

    public function __construct(
        private readonly CastorApiClient $castorApi,
    ) {}

    /**
     * @param array<string, mixed>       $state
     * @param list<array<string, mixed>> $plugins
     */
    public function mount(array $state, array $plugins): void
    {
        $this->state = $state;
        $this->plugins = $plugins;
        $this->selected = $this->getInstalled();
    }

    /**
     * Back to the installed plugins. The search stays.
     */
    #[LiveAction]
    public function discard(): void
    {
        $this->selected = $this->getInstalled();
        $this->error = null;
    }

    /**
     * @return list<string> the checked plugins that are not installed, in the order of the catalog
     */
    public function getToInstall(): array
    {
        return array_values(array_diff(array_intersect(array_column($this->plugins, 'name'), $this->selected), $this->getInstalled()));
    }

    /**
     * @return list<string> the installed plugins of the catalog that are unchecked
     */
    public function getToRemove(): array
    {
        return array_values(array_diff(array_intersect($this->getInstalled(), array_column($this->plugins, 'name')), $this->selected));
    }

    /**
     * @return list<string>
     */
    public function getInstalled(): array
    {
        return array_values(array_filter((array) ($this->state['plugins'] ?? []), \is_string(...)));
    }

    /**
     * The plugins whose name or description contain every word of the search.
     *
     * @return list<array<string, mixed>>
     */
    public function getResults(): array
    {
        $words = array_filter(preg_split('/\s+/', mb_strtolower(trim($this->query))) ?: []);

        return array_values(array_filter($this->plugins, static function (array $plugin) use ($words): bool {
            $haystack = mb_strtolower(str_replace('_', ' ', (string) ($plugin['name'] ?? '')) . ' ' . ($plugin['name'] ?? '') . ' ' . ($plugin['description'] ?? ''));

            foreach ($words as $word) {
                if (!str_contains($haystack, $word)) {
                    return false;
                }
            }

            return true;
        }));
    }

    protected function change(array $state): array
    {
        $known = array_column($this->plugins, 'name');
        $state['plugins'] = array_values(array_intersect($known, $this->selected));

        // Installed plugins the catalog does not know stay as they are.
        foreach ($this->getInstalled() as $plugin) {
            if (!\in_array($plugin, $known, true)) {
                $state['plugins'][] = $plugin;
            }
        }

        return $state;
    }
}
