<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Twig\Extension;

use OpenColibri\Starter\Datasets\DatasetPreview;
use OpenColibri\Starter\DevKitBundle\Castor\CastorApiClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * The data of the dev-kit pages, read through castor-api once per request: what the shop runs now, what it can pick
 * (cached a few minutes: it only changes with the installed packages), and the history.
 */
final class DevKitExtension
{
    /** What the manifest can pick, cached: see catalogCacheKey(). */
    public const string CATALOG_CACHE_KEY = 'opencolibri_dev_kit.catalog';

    /** @var array<string, mixed> */
    private array $loaded = [];

    public function __construct(
        private readonly CastorApiClient $castorApi,
        private readonly CacheInterface $cache,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire('%opencolibri_dev_kit.storefront_url%')]
        private readonly string $storefrontUrl,
        #[Autowire('%opencolibri_dev_kit.import_var_dir%')]
        private readonly string $importVarDir,
    ) {}

    /**
     * What the shop runs now, or null when castor-api cannot tell (see dev_kit_error()).
     *
     * @return array<string, mixed>|null
     */
    #[AsTwigFunction('dev_kit_state')]
    public function state(): ?array
    {
        return $this->load('state', fn(): array => $this->castorApi->status());
    }

    /**
     * @return array{themes: list<array<string, mixed>>, datasets: list<array<string, mixed>>, plugins: list<array<string, mixed>>}
     */
    #[AsTwigFunction('dev_kit_catalog')]
    public function catalog(): array
    {
        $empty = ['themes' => [], 'datasets' => [], 'plugins' => []];

        return $this->load('catalog', fn(): array => $this->cache->get($this->catalogCacheKey(), function (ItemInterface $item): array {
            $item->expiresAfter(300);

            return $this->castorApi->catalog();
        })) ?? $empty;
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[AsTwigFunction('dev_kit_history')]
    public function history(): array
    {
        return $this->load('history', fn(): array => $this->castorApi->history()) ?? [];
    }

    /**
     * The datasets built in the project change from the admin or from a run (an AI build, an import): the cache key
     * follows the files of their directory, so the list is read again as soon as one of them is added, changed or
     * removed.
     */
    private function catalogCacheKey(): string
    {
        $directory = \dirname(rtrim($this->importVarDir, '/\\')) . '/datasets';
        $files = [$directory];

        foreach (['dataset', 'products', 'collections'] as $file) {
            array_push($files, ...(glob($directory . '/*/' . $file . '.yaml') ?: []));
        }

        $times = array_map(static fn(string $file): string => $file . '@' . (@filemtime($file) ?: 0), $files);

        return self::CATALOG_CACHE_KEY . '.' . hash('xxh128', implode('|', $times));
    }

    /**
     * Why castor-api could not answer, if it could not.
     */
    #[AsTwigFunction('dev_kit_error')]
    public function error(): ?string
    {
        $this->state();

        return \is_string($this->loaded['error'] ?? null) ? $this->loaded['error'] : null;
    }

    #[AsTwigFunction('dev_kit_storefront_url')]
    public function storefrontUrl(): string
    {
        return $this->storefrontUrl;
    }

    /**
     * The thumbnail URL of a ready-made dataset, or null for a dataset built here.
     */
    #[AsTwigFunction('dev_kit_dataset_preview')]
    public function datasetPreview(string $dataset): ?string
    {
        return null !== DatasetPreview::path($dataset) ? $this->urlGenerator->generate('opencolibri_dev_kit_dataset_preview', ['dataset' => $dataset]) : null;
    }

    /**
     * @template T of array
     *
     * @param callable(): T $loader
     *
     * @return T|null
     */
    private function load(string $key, callable $loader): ?array
    {
        if (!\array_key_exists($key, $this->loaded)) {
            try {
                $this->loaded[$key] = $loader();
            } catch (\RuntimeException $exception) {
                $this->loaded[$key] = null;
                $this->loaded['error'] ??= $exception->getMessage();
            }
        }

        /** @var T|null */
        return $this->loaded[$key];
    }
}
