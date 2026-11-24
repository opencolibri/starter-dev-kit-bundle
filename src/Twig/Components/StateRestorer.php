<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Twig\Components;

use OpenColibri\Starter\DevKitBundle\Castor\CastorApiClient;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Brings the shop back to a state of the history: the plan shows once asked for, then runs like any change.
 */
#[AsLiveComponent]
final class StateRestorer
{
    use DefaultActionTrait;
    use PlansChangeTrait;

    /** @var array<string, mixed> the state of the history entry */
    #[LiveProp]
    public array $target = [];

    #[LiveProp]
    public bool $planned = false;

    public function __construct(
        private readonly CastorApiClient $castorApi,
    ) {}

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $target
     */
    public function mount(array $state, array $target): void
    {
        $this->state = $state;
        $this->target = $target;
    }

    #[LiveAction]
    public function showPlan(): void
    {
        $this->planned = true;
    }

    protected function change(array $state): array
    {
        return $this->planned ? $this->target : $state;
    }
}
