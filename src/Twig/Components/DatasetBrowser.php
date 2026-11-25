<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Twig\Components;

use OpenColibri\Starter\DevKitBundle\Controller\DatasetController;
use OpenColibri\Starter\DevKitBundle\Twig\Extension\DevKitExtension;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The datasets the shop can load, searched as you type, each opening its page. They are read from dev-kit:catalog on
 * every render, not kept in the props: their entries hold paths of the project.
 */
#[AsLiveComponent]
final class DatasetBrowser
{
    use DefaultActionTrait;

    /** The dataset the shop runs now, if known. */
    #[LiveProp]
    public ?string $current = null;

    #[LiveProp(writable: true)]
    public string $query = '';

    public function __construct(
        private readonly DevKitExtension $devKit,
        private readonly TranslatorInterface $translator,
    ) {}

    /**
     * The Sylius demo data, then the datasets: slug, name, and the figures of dev-kit:catalog.
     *
     * @return list<array<string, mixed>>
     */
    public function getDatasets(): array
    {
        return [
            ['slug' => DatasetController::SYLIUS_FIXTURES, 'name' => $this->translator->trans('opencolibri_dev_kit.ui.sylius_fixtures')],
            ...$this->devKit->catalog()['datasets'],
        ];
    }

    /**
     * The datasets whose name, slug, description or theme contain every word of the search.
     *
     * @return list<array<string, mixed>>
     */
    public function getResults(): array
    {
        $words = array_filter(preg_split('/\s+/', mb_strtolower(trim($this->query))) ?: []);

        return array_values(array_filter($this->getDatasets(), static function (array $dataset) use ($words): bool {
            $haystack = mb_strtolower(implode(' ', array_map(
                static fn(string $key): string => \is_string($dataset[$key] ?? null) ? str_replace(['-', '_'], ' ', $dataset[$key]) . ' ' . $dataset[$key] : '',
                ['name', 'slug', 'description', 'theme'],
            )));

            foreach ($words as $word) {
                if (!str_contains($haystack, $word)) {
                    return false;
                }
            }

            return true;
        }));
    }
}
