<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Twig\Components;

use OpenColibri\Starter\DevKitBundle\Castor\CastorApiClient;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;

/**
 * A card that changes one setting of the shop: it shows the plan of dev-kit:apply for the wanted state (the current
 * state with this change), with the cost of each step, and starts it when applied. RunMonitor follows the run and
 * reloads the page once it finishes.
 *
 * @property CastorApiClient $castorApi
 */
trait PlansChangeTrait
{
    use StartsRunTrait;

    /**
     * What the shop runs now, as dev-kit:status prints it.
     *
     * @var array<string, mixed>
     */
    #[LiveProp]
    public array $state = [];

    /** @var array{steps: list<array<string, mixed>>, errors: list<string>}|null */
    private ?array $plan = null;

    /**
     * The wanted state: the current one with the change of this card.
     *
     * @param array<string, mixed> $state
     *
     * @return array<string, mixed>
     */
    abstract protected function change(array $state): array;

    /**
     * @return array<string, mixed>
     */
    public function getWanted(): array
    {
        return $this->change($this->state);
    }

    public function hasChanges(): bool
    {
        return self::normalized($this->getWanted()) !== self::normalized($this->state);
    }

    /**
     * @return array{steps: list<array<string, mixed>>, errors: list<string>}|null
     */
    public function getPlan(): ?array
    {
        if (!$this->hasChanges()) {
            return null;
        }

        if (null === $this->plan) {
            try {
                $this->plan = $this->castorApi->plan($this->getWanted());
            } catch (\RuntimeException $exception) {
                $this->plan = ['steps' => [], 'errors' => [$exception->getMessage()]];
            }
        }

        return $this->plan;
    }

    #[LiveAction]
    public function apply(): void
    {
        $plan = $this->getPlan();

        if (null === $plan || [] !== $plan['errors'] || [] === $plan['steps']) {
            return;
        }

        $this->startRun(CastorApiClient::APPLY, CastorApiClient::applyPayload($this->getWanted()));
    }

    /**
     * Lists compared as sets: the order of the locales or plugins is not a change, the first currency is.
     *
     * @param array<mixed> $value
     *
     * @return array<mixed>
     */
    private static function normalized(array $value): array
    {
        ksort($value);

        foreach ($value as $key => $item) {
            if (\is_array($item)) {
                $value[$key] = array_is_list($item) && 'currencies' !== $key ? self::sortedList($item) : self::normalized($item);
            }
        }

        return $value;
    }

    /**
     * @param list<mixed> $list
     *
     * @return list<mixed>
     */
    private static function sortedList(array $list): array
    {
        sort($list);

        return $list;
    }
}
