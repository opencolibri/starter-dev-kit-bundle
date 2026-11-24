<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Tests\Functional;

use OpenColibri\Starter\DevKitBundle\Tests\Support\FakeCastorApi;

final class DashboardTest extends WebTestCase
{
    public function testItAsksAnonymousVisitorsToLogIn(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }

    public function testItSumsUpTheShopWithALinkToEachSettingsPage(): void
    {
        $crawler = static::createAdminClient()->request('GET', '/');

        self::assertResponseIsSuccessful();
        static::assertSame(
            ['Storefront', 'Catalog', 'Shop', 'Locales and currencies', 'Plugins'],
            $crawler->filter('main [data-slot="card-title"]')->each(static fn($title): string => trim($title->text())),
        );
        $text = (string) preg_replace('/\s+/', ' ', $crawler->filter('main')->text());
        static::assertStringContainsString('Roastery', $text);
        static::assertStringContainsString('Maison Roastery', $text);
        static::assertStringContainsString('16 products', $text);
        foreach (['storefront', 'catalog', 'shop', 'locales', 'plugins'] as $page) {
            self::assertSelectorExists(\sprintf('main a[href="/settings/%s"]', $page));
        }
        self::assertSelectorExists('a[href="https://shop.test/"]');
        // The dashboard only reads: no card changes anything there.
        self::assertSelectorNotExists('main input#shop-name');
    }

    public function testItAsksForNoPlanWhileNothingChanges(): void
    {
        static::createAdminClient()->request('GET', '/');

        static::assertSame([], FakeCastorApi::calls('dev-kit:apply'));
    }

    public function testItExplainsWhenCastorApiDoesNotAnswer(): void
    {
        FakeCastorApi::fail('dev-kit:status', 'Unable to reach castor-api.');

        static::createAdminClient()->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'castor-api does not answer');
        self::assertSelectorTextContains('main', 'castor api:serve-development');
        self::assertSelectorNotExists('input#shop-name');
    }

    public function testTheSidebarListsTheSettingsPagesAndTheHistory(): void
    {
        $crawler = static::createAdminClient()->request('GET', '/');

        $links = $crawler->filter('a[href^="/settings/"], a[href="/history"]')->each(static fn($link): string => (string) $link->attr('href'));
        foreach (['/settings/storefront', '/settings/catalog', '/settings/locales', '/settings/plugins', '/settings/shop', '/history'] as $href) {
            static::assertContains($href, $links);
        }
    }
}
