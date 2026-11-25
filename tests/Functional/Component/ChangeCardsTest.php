<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Tests\Functional\Component;

use OpenColibri\Starter\DevKitBundle\Tests\Application\Kernel;
use OpenColibri\Starter\DevKitBundle\Tests\Support\FakeCastorApi;

final class ChangeCardsTest extends ComponentTestCase
{
    /** @var array<string, mixed> */
    private array $state;

    /** @var array<string, mixed> */
    private array $catalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->state = json_decode((string) file_get_contents(Kernel::FIXTURES_DIR . '/castor-api/status.json'), true, flags: \JSON_THROW_ON_ERROR);
        $this->catalog = json_decode((string) file_get_contents(Kernel::FIXTURES_DIR . '/castor-api/catalog.json'), true, flags: \JSON_THROW_ON_ERROR);
    }

    public function testThePluginSearchFiltersAsYouType(): void
    {
        $component = $this->createAdminLiveComponent('DevKit:PluginsPicker', ['state' => $this->state, 'plugins' => $this->catalog['plugins']]);

        static::assertCount(4, $component->render()->crawler()->filter('input[name="plugins[]"]'));

        $component->set('query', 'captcha');
        $names = $component->render()->crawler()->filter('input[name="plugins[]"]')->each(static fn($input): string => (string) $input->attr('value'));
        static::assertSame(['altcha', 'recaptcha'], $names);

        $component->set('query', 'nothing like it');
        static::assertStringContainsString('No plugin matches &quot;nothing like it&quot;.', $component->render()->toString());
    }

    public function testAnInstalledPluginWithoutRemoverCannotBeUnchecked(): void
    {
        $crawler = $this->createAdminLiveComponent('DevKit:PluginsPicker', ['state' => $this->state, 'plugins' => $this->catalog['plugins']])->render()->crawler();

        static::assertNotNull($crawler->filter('input[value="refund"]')->attr('disabled'));
        static::assertNull($crawler->filter('input[value="altcha"]')->attr('disabled'));
    }

    public function testCheckingAPluginShowsThePlanThenStartsIt(): void
    {
        $component = $this->createAdminLiveComponent('DevKit:PluginsPicker', ['state' => $this->state, 'plugins' => $this->catalog['plugins']]);
        $component->set('selected', ['altcha', 'refund', 'wishlist']);

        static::assertStringContainsString('Change the header layout', $component->render()->toString(), 'The plan of the fixture shows.');
        $wanted = json_decode((string) FakeCastorApi::calls('dev-kit:apply')[0]['payload']['manifest-json'], true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame(['altcha', 'refund', 'wishlist'], $wanted['plugins']);

        $component->call('apply');

        $started = array_values(array_filter(FakeCastorApi::calls('dev-kit:apply'), static fn(array $call): bool => 'start' === $call['action']));
        static::assertCount(1, $started);
        static::assertTrue($started[0]['payload']['yes']);
    }

    public function testTheHeaderLayoutAppliesWithAButtonOnly(): void
    {
        $component = $this->createAdminLiveComponent('DevKit:HeaderLayoutPicker', ['state' => $this->state, 'themes' => $this->catalog['themes']]);
        $component->set('search', 'icon');

        static::assertSame([], array_filter(FakeCastorApi::calls('dev-kit:apply'), static fn(array $call): bool => 'start' === $call['action']));
        $wanted = json_decode((string) FakeCastorApi::calls('dev-kit:apply')[0]['payload']['manifest-json'], true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame(['name' => 'roastery', 'header' => ['menu' => 'below', 'search' => 'icon']], $wanted['theme']);
        static::assertStringContainsString('Apply', $component->render()->toString());
    }

    public function testANewThemeKeepsTheHeaderLayoutItSupports(): void
    {
        $component = $this->createAdminLiveComponent('DevKit:ThemePicker', ['state' => $this->state, 'themes' => $this->catalog['themes']]);
        $component->set('theme', 'drive');
        $component->render();

        $wanted = json_decode((string) FakeCastorApi::calls('dev-kit:apply')[0]['payload']['manifest-json'], true, flags: \JSON_THROW_ON_ERROR);
        // Drive has no "inline" menu… but supports "below" and the inline search.
        static::assertSame(['name' => 'drive', 'header' => ['menu' => 'below', 'search' => 'inline']], $wanted['theme']);
    }

    public function testTheBaseCurrencyComesFirst(): void
    {
        $component = $this->createAdminLiveComponent('DevKit:LocalesPicker', ['state' => $this->state]);
        $component->set('baseCurrency', 'USD');
        $component->render();

        $wanted = json_decode((string) FakeCastorApi::calls('dev-kit:apply')[0]['payload']['manifest-json'], true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame(['USD', 'EUR'], $wanted['currencies']);
    }

    public function testReorderingTheLocalesIsNoChange(): void
    {
        $component = $this->createAdminLiveComponent('DevKit:LocalesPicker', ['state' => $this->state]);
        $component->set('locales', ['fr_FR', 'en_US']);
        $component->render();

        static::assertSame([], FakeCastorApi::calls('dev-kit:apply'));
    }

    public function testTheDatasetSearchFiltersAsYouType(): void
    {
        $component = $this->createAdminLiveComponent('DevKit:DatasetBrowser', ['current' => 'roastery']);
        $titles = static fn(): array => $component->render()->crawler()->filter('ul.grid a .truncate')->each(static fn($title): string => trim($title->text()));

        static::assertSame(['Sylius demo data', 'Roastery', 'Teahouse', 'My catalog'], $titles());

        // Every word, in the name, slug, description or theme.
        $component->set('query', 'written hand');
        static::assertSame(['My catalog'], $titles());
        $component->set('query', 'tea');
        static::assertSame(['Teahouse'], $titles());

        $component->set('query', 'bicycles');
        static::assertStringContainsString('No dataset matches &quot;bicycles&quot;.', $component->render()->toString());
    }

    public function testALoadedDatasetBuiltInTheProjectCanBeLoadedAgain(): void
    {
        $state = ['dataset' => 'my-catalog'] + $this->state;
        $component = $this->createAdminLiveComponent('DevKit:DatasetLoader', ['state' => $state, 'dataset' => 'my-catalog', 'editable' => true]);

        static::assertStringContainsString('Load it again', $component->render()->toString());
        $component->call('reload');

        static::assertSame(['dataset' => 'my-catalog'], FakeCastorApi::calls('sylius:dataset:load')[0]['payload']);
        static::assertSame('start', FakeCastorApi::calls('sylius:dataset:load')[0]['action']);

        // A ready-made dataset only loads through dev-kit:apply.
        $readyMade = $this->createAdminLiveComponent('DevKit:DatasetLoader', ['state' => $this->state, 'dataset' => 'roastery']);
        static::assertStringNotContainsString('Load it again', $readyMade->render()->toString());
    }

    public function testTheHistoryPlansTheWayBackOnceAsked(): void
    {
        $history = json_decode((string) file_get_contents(Kernel::FIXTURES_DIR . '/castor-api/history.json'), true, flags: \JSON_THROW_ON_ERROR);
        $component = $this->createAdminLiveComponent('DevKit:StateRestorer', ['state' => $this->state, 'target' => $history[1]['state']]);

        static::assertStringContainsString('Back to this state', $component->render()->toString());
        static::assertSame([], FakeCastorApi::calls('dev-kit:apply'));

        $component->call('showPlan');

        $wanted = json_decode((string) FakeCastorApi::calls('dev-kit:apply')[0]['payload']['manifest-json'], true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame(['name' => 'canvas'], $wanted['theme']);
    }
}
