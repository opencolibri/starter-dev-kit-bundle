<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Tests\Functional;

final class HistoryTest extends WebTestCase
{
    public function testItListsTheStatesTheLatestFirst(): void
    {
        $crawler = static::createAdminClient()->request('GET', '/history');

        self::assertResponseIsSuccessful();
        $entries = $crawler->filter('main ol.divide-y > li');
        static::assertCount(2, $entries);
        static::assertStringContainsString('Change the header layout', $entries->eq(0)->text());
        static::assertStringContainsString('Current state', $entries->eq(0)->text());
        static::assertStringContainsString('Apply the canvas theme', $entries->eq(1)->text());
        static::assertStringContainsString('Back to this state', $entries->eq(1)->text());
    }
}
