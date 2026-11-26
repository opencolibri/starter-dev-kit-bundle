<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Twig\Components;

use Symfony\UX\LiveComponent\Attribute\LiveProp;

/**
 * Items of dev-kit:catalog (themes, plugins) grouped by their "category" (name, label, description), in the order of the
 * catalog, and filtered on one of them. The items of no category (custom ones) are under "other", last.
 */
trait FiltersByCategoryTrait
{
    /** The category shown, or '' for every one. */
    #[LiveProp(writable: true)]
    public string $category = '';

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return list<array{name: string, label: ?string, description: ?string, count: int}> the categories, with the number of their items
     */
    private static function categoriesOf(array $items): array
    {
        $categories = [];

        foreach ($items as $item) {
            $category = self::categoryOf($item);
            $categories[$category['name']] ??= $category + ['count' => 0];
            ++$categories[$category['name']]['count'];
        }

        if (isset($categories['other'])) {
            $other = $categories['other'];
            unset($categories['other']);
            $categories['other'] = $other;
        }

        return array_values($categories);
    }

    /**
     * @param list<array<string, mixed>> $all   every item, for the categories
     * @param list<array<string, mixed>> $shown the items to group (a search result, say)
     *
     * @return list<array{name: string, label: ?string, description: ?string, items: list<array<string, mixed>>}> the
     *                                                                                                              groups of the picked category, or of all of them, without the empty ones
     */
    private function groupsOf(array $all, array $shown): array
    {
        $categories = self::categoriesOf($all);
        // An unknown category (gone from the catalog) shows every item.
        $picked = $this->pickedCategory($categories);
        $groups = [];

        foreach ($categories as $category) {
            if ('' !== $picked && $category['name'] !== $picked) {
                continue;
            }

            $items = array_values(array_filter($shown, static fn(array $item): bool => self::categoryOf($item)['name'] === $category['name']));

            if ([] !== $items) {
                unset($category['count']);
                $groups[] = $category + ['items' => $items];
            }
        }

        return $groups;
    }

    /**
     * @param list<array{name: string}> $categories
     *
     * @return string the picked category, or '' when it is none of them
     */
    private function pickedCategory(array $categories): string
    {
        return \in_array($this->category, array_column($categories, 'name'), true) ? $this->category : '';
    }

    /**
     * @param array<string, mixed> $item
     *
     * @return array{name: string, label: ?string, description: ?string}
     */
    private static function categoryOf(array $item): array
    {
        $category = $item['category'] ?? null;

        if (!\is_array($category) || !\is_string($category['name'] ?? null) || '' === $category['name']) {
            return ['name' => 'other', 'label' => null, 'description' => null];
        }

        return [
            'name' => $category['name'],
            'label' => \is_string($category['label'] ?? null) ? $category['label'] : null,
            'description' => \is_string($category['description'] ?? null) ? $category['description'] : null,
        ];
    }
}
