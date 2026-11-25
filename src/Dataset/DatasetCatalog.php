<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Dataset;

/**
 * What a dataset holds: its collections and its products, as written in its collections.yaml and products.yaml.
 * Images are file names of its media/ directory, or null when the image was not downloaded yet.
 */
final readonly class DatasetCatalog
{
    /**
     * @param list<array{index: int, name: string, key: string, parent: string|null, description: string|null, image: string|null, productCount: int}>                $collections
     * @param list<array{index: int, title: string, description: string|null, collection: string|null, collectionKey: string|null, price: float|null, image: string|null, imageSource: 'url'|'prompt'|null}> $products
     */
    public function __construct(
        public array $collections,
        public array $products,
    ) {}

    /**
     * @return list<array{index: int, title: string, description: string|null, collection: string|null, collectionKey: string|null, price: float|null, image: string|null, imageSource: 'url'|'prompt'|null}>
     */
    public function productsOf(?string $collection): array
    {
        if (null === $collection) {
            return $this->products;
        }

        return array_values(array_filter($this->products, static fn(array $product): bool => $product['collection'] === $collection));
    }

    /**
     * @return array{index: int, title: string, description: string|null, collection: string|null, collectionKey: string|null, price: float|null, image: string|null, imageSource: 'url'|'prompt'|null}|null
     */
    public function product(int $index): ?array
    {
        foreach ($this->products as $product) {
            if ($product['index'] === $index) {
                return $product;
            }
        }

        return null;
    }

    /**
     * @return array{index: int, name: string, key: string, parent: string|null, description: string|null, image: string|null, productCount: int}|null
     */
    public function collection(int $index): ?array
    {
        foreach ($this->collections as $collection) {
            if ($collection['index'] === $index) {
                return $collection;
            }
        }

        return null;
    }

    /**
     * The collections a product can go in, as form choices: label => name or code. A sub-collection is labelled
     * after its parent ("Men › T-shirts").
     *
     * @return array<string, string>
     */
    public function collectionChoices(): array
    {
        $labels = [];

        foreach ($this->collections as $collection) {
            $labels[$collection['key']] = $collection['name'];
        }

        $choices = [];

        foreach ($this->collections as $collection) {
            $label = null !== $collection['parent'] ? ($labels[$collection['parent']] ?? $collection['parent']) . ' › ' . $collection['name'] : $collection['name'];
            $choices[$label] = $collection['key'];
        }

        return $choices;
    }

    /**
     * The products whose image sylius:dataset:images can store, by where it comes from.
     *
     * @return array{prompt: int, url: int}
     */
    public function fetchableImages(): array
    {
        $counts = ['prompt' => 0, 'url' => 0];

        foreach ($this->products as $product) {
            if (null !== $product['imageSource']) {
                ++$counts[$product['imageSource']];
            }
        }

        return $counts;
    }
}
