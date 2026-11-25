<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Twig\Components;

use OpenColibri\Starter\DevKitBundle\Castor\CastorApiClient;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Loads the dataset of its page into the shop: the plan shows once asked for, then runs like any change. A dataset
 * built in the project may change once loaded: it is then loaded again, as dev-kit:apply only compares its slug.
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

    /** Built in the project, so editable: it can be loaded again after a change. */
    #[LiveProp]
    public bool $editable = false;

    public function __construct(
        private readonly CastorApiClient $castorApi,
    ) {}

    /**
     * @param array<string, mixed> $state
     */
    public function mount(array $state, string $dataset, bool $editable = false): void
    {
        $this->state = $state;
        $this->dataset = $dataset;
        $this->editable = $editable;
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

    /**
     * Loads the dataset again, with the changes made to it since it was loaded.
     */
    #[LiveAction]
    public function reload(): void
    {
        if ($this->editable && $this->isLoaded()) {
            $this->startRun('sylius:dataset:load', ['dataset' => $this->dataset]);
        }
    }

    protected function change(array $state): array
    {
        if ($this->planned) {
            $state['dataset'] = $this->dataset;
        }

        return $state;
    }
}
