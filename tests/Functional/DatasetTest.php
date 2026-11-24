<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Tests\Functional;

final class DatasetTest extends WebTestCase
{
    public function testItShowsTheProductsOfADatasetBeforeItIsLoaded(): void
    {
        $crawler = static::createAdminClient()->request('GET', '/catalog/teahouse');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Teahouse');
        static::assertGreaterThan(5, $crawler->filter('main ul.grid > li')->count());
        self::assertSelectorExists('main img[src^="/catalog/teahouse/media/"]');
        self::assertSelectorTextContains('main', 'Load this catalog');
    }

    public function testItFiltersTheProductsByCollection(): void
    {
        $client = static::createAdminClient();
        $all = $client->request('GET', '/catalog/teahouse')->filter('main ul.grid > li')->count();
        $link = $client->getCrawler()->filter('main nav[aria-label="Collections"] a')->eq(1);

        $filtered = $client->request('GET', (string) $link->attr('href'))->filter('main ul.grid > li')->count();

        static::assertLessThan($all, $filtered);
        static::assertGreaterThan(0, $filtered);
    }

    public function testTheLoadedDatasetSaysSo(): void
    {
        static::createAdminClient()->request('GET', '/catalog/roastery');

        self::assertSelectorTextContains('main', 'Loaded in the shop');
        self::assertSelectorTextNotContains('main', 'Load this catalog');
    }

    public function testItServesTheImagesOfTheDataset(): void
    {
        $client = static::createAdminClient();
        $src = (string) $client->request('GET', '/catalog/teahouse')->filter('main img[src^="/catalog/teahouse/media/"]')->first()->attr('src');

        $client->request('GET', $src);

        self::assertResponseIsSuccessful();
    }

    public function testTheCatalogPageOpensEachDataset(): void
    {
        $crawler = static::createAdminClient()->request('GET', '/settings/catalog');

        $links = $crawler->filter('main ul.grid a')->each(static fn($link): string => (string) $link->attr('href'));
        static::assertSame(['/catalog/sylius', '/catalog/roastery', '/catalog/teahouse'], $links);
        self::assertSelectorNotExists('main input[type="radio"]');
    }

    public function testTheSyliusDemoDataHaveAPageToLoadThem(): void
    {
        static::createAdminClient()->request('GET', '/catalog/sylius');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Sylius demo data');
        self::assertSelectorTextContains('main', 'purges the whole database');
        self::assertSelectorTextContains('main', 'Load this catalog');
    }

    public function testAnUnknownDatasetIsNotFound(): void
    {
        static::createAdminClient()->request('GET', '/catalog/nope');

        self::assertResponseStatusCodeSame(404);
    }
}
