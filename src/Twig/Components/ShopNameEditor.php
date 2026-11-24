<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Twig\Components;

use OpenColibri\Starter\DevKitBundle\Castor\CastorApiClient;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The name of the shop: the name of its default channel.
 */
#[AsLiveComponent]
final class ShopNameEditor
{
    use DefaultActionTrait;
    use PlansChangeTrait;

    #[LiveProp(writable: true)]
    public string $name = '';

    public function __construct(
        private readonly CastorApiClient $castorApi,
    ) {}

    /**
     * @param array<string, mixed> $state
     */
    public function mount(array $state): void
    {
        $this->state = $state;
        $this->name = (string) ($state['shop']['name'] ?? '');
    }

    protected function change(array $state): array
    {
        if ('' !== trim($this->name)) {
            $state['shop'] = ['name' => trim($this->name)];
        }

        return $state;
    }
}
