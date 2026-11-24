<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Twig\Components;

use OpenColibri\Starter\DevKitBundle\Castor\CastorApiClient;
use Symfony\Component\Intl\Currencies;
use Symfony\Component\Intl\Locales;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The locales and currencies of the default channel. The first currency is the base one.
 */
#[AsLiveComponent]
final class LocalesPicker
{
    use DefaultActionTrait;
    use PlansChangeTrait;

    /** The locales and currencies offered, on top of those the channel already enables. */
    public const array LOCALES = ['en_US', 'fr_FR', 'de_DE', 'es_ES', 'it_IT', 'nl_NL', 'pt_PT', 'pl_PL'];
    public const array CURRENCIES = ['EUR', 'USD', 'GBP', 'CHF'];

    /** @var list<string> */
    #[LiveProp(writable: true)]
    public array $locales = [];

    #[LiveProp(writable: true)]
    public string $defaultLocale = '';

    /** @var list<string> */
    #[LiveProp(writable: true)]
    public array $currencies = [];

    #[LiveProp(writable: true)]
    public string $baseCurrency = '';

    public function __construct(
        private readonly CastorApiClient $castorApi,
    ) {}

    /**
     * @param array<string, mixed> $state
     */
    public function mount(array $state): void
    {
        $this->state = $state;
        $this->locales = array_values((array) ($state['locales']['enabled'] ?? []));
        $this->defaultLocale = (string) ($state['locales']['default'] ?? '');
        $this->currencies = array_values((array) ($state['currencies'] ?? []));
        $this->baseCurrency = $this->currencies[0] ?? '';
    }

    /**
     * @return array<string, string> code => name, in the language of the admin
     */
    public function getLocaleChoices(): array
    {
        $codes = array_values(array_unique([...self::LOCALES, ...(array) ($this->state['locales']['enabled'] ?? [])]));

        return array_combine($codes, array_map(static fn(string $code): string => Locales::exists($code) ? Locales::getName($code) : $code, $codes));
    }

    /**
     * @return array<string, string> code => name
     */
    public function getCurrencyChoices(): array
    {
        $codes = array_values(array_unique([...self::CURRENCIES, ...(array) ($this->state['currencies'] ?? [])]));

        return array_combine($codes, array_map(static fn(string $code): string => Currencies::exists($code) ? Currencies::getName($code) : $code, $codes));
    }

    protected function change(array $state): array
    {
        $locales = array_values(array_intersect(array_keys($this->getLocaleChoices()), $this->locales));
        $currencies = array_values(array_intersect(array_keys($this->getCurrencyChoices()), $this->currencies));

        if ([] === $locales || [] === $currencies) {
            return $state;
        }

        $default = \in_array($this->defaultLocale, $locales, true) ? $this->defaultLocale : $locales[0];
        $base = \in_array($this->baseCurrency, $currencies, true) ? $this->baseCurrency : $currencies[0];
        $state['locales'] = ['default' => $default, 'enabled' => $locales];
        $state['currencies'] = [$base, ...array_values(array_diff($currencies, [$base]))];

        return $state;
    }
}
