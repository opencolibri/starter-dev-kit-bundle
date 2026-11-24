<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Tests\Functional;

use OpenColibri\Starter\DevKitBundle\Tests\Support\FakeCastorApi;
use PHPUnit\Framework\Attributes\DataProvider;

final class SettingsTest extends WebTestCase
{
    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function pages(): iterable
    {
        yield 'storefront' => ['storefront', 'Storefront', ['Storefront theme', 'Header layout']];
        yield 'catalog' => ['catalog', 'Catalog', ['Catalog']];
        yield 'locales' => ['locales', 'Locales and currencies', ['Locales and currencies']];
        yield 'plugins' => ['plugins', 'Plugins', ['Plugins']];
        yield 'shop' => ['shop', 'Shop', ['Shop name']];
    }

    /**
     * @param list<string> $cards
     */
    #[DataProvider('pages')]
    public function testEachPageShowsTheCardsOfItsSettings(string $page, string $title, array $cards): void
    {
        $crawler = static::createAdminClient()->request('GET', '/settings/' . $page);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $title);
        static::assertSame($cards, $crawler->filter('main [data-slot="card-title"]')->each(static fn($card): string => trim($card->text())));
    }

    public function testAnUnknownPageIsNotFound(): void
    {
        static::createAdminClient()->request('GET', '/settings/checkout');

        self::assertResponseStatusCodeSame(404);
    }

    public function testAPageExplainsWhenCastorApiDoesNotAnswer(): void
    {
        FakeCastorApi::fail('dev-kit:status', 'Unable to reach castor-api.');

        static::createAdminClient()->request('GET', '/settings/plugins');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'castor-api does not answer');
    }
}
