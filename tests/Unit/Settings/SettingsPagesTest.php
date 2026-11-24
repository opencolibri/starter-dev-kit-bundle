<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Tests\Unit\Settings;

use OpenColibri\Starter\DevKitBundle\Settings\SettingsPages;
use PHPUnit\Framework\TestCase;

final class SettingsPagesTest extends TestCase
{
    public function testAnAddedPageTakesItsPlaceByPriority(): void
    {
        $pages = new SettingsPages(array_replace(SettingsPages::DEFAULTS, [
            'checkout' => ['title' => 'Checkout rules', 'subheader' => null, 'icon' => 'lucide:shopping-cart', 'priority' => 250],
        ]));

        static::assertSame(['storefront', 'catalog', 'locales', 'checkout', 'plugins', 'shop'], array_keys($pages->all()));
        static::assertSame('Checkout rules', $pages->get('checkout')['title'] ?? null);
        static::assertNull($pages->get('nope'));
    }
}
