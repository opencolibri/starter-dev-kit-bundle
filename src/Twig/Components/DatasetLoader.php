<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Twig\Components;

use OpenColibri\Starter\DevKitBundle\Castor\CastorApiClient;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Loads the dataset of its page into the shop: the plan shows once asked for, then runs like any change.
 */
#[AsLiveComponent]
final class DatasetLoader
{
    use DefaultActionTrait;
    use PlansChangeTrait;

    #[LiveProp]
    public string $dataset = '';

    #[LiveProp]
    public bool $planned = false;

    public function __construct(
        private readonly CastorApiClient $castorApi,
    ) {}

    /**
     * @param array<string, mixed> $state
     */
    public function mount(array $state, string $dataset): void
    {
        $this->state = $state;
        $this->dataset = $dataset;
    }

    public function isLoaded(): bool
    {
        return ($this->state['dataset'] ?? null) === $this->dataset;
    }

    #[LiveAction]
    public function showPlan(): void
    {
        $this->planned = true;
    }

    protected function change(array $state): array
    {
        if ($this->planned) {
            $state['dataset'] = $this->dataset;
        }

        return $state;
    }
}
