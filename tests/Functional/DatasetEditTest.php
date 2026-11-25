<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Tests\Functional;

use OpenColibri\Starter\DevKitBundle\Tests\Application\Kernel;
use OpenColibri\Starter\DevKitBundle\Tests\Support\FakeCastorApi;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class DatasetEditTest extends WebTestCase
{
    private const UPLOADS = Kernel::FIXTURES_DIR . '/import-var/.uploads';

    private string $temporary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->temporary = sys_get_temp_dir() . '/dev-kit-dataset-edit-' . bin2hex(random_bytes(4));
        mkdir($this->temporary);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove([self::UPLOADS, $this->temporary]);
        parent::tearDown();
    }

    public function testTheCatalogPageLinksToTheNewDatasetForms(): void
    {
        static::createAdminClient()->request('GET', '/settings/catalog');

        foreach (['/catalog/new/import', '/catalog/new/manual', '/catalog/new/existing', '/catalog/new/ai'] as $url) {
            self::assertSelectorExists(\sprintf('a[href="%s"]', $url));
        }
    }

    public function testItBuildsADatasetWithTheAiAndFollowsTheRun(): void
    {
        $client = static::createAdminClient();
        $client->request('GET', '/catalog/new/ai');
        self::assertResponseIsSuccessful();

        $client->submitForm('Build dataset', [
            'dataset_ai[name]' => 'Tea House',
            'dataset_ai[description]' => 'Loose leaf teas and tea ware',
            'dataset_ai[limit]' => '12',
        ]);

        self::assertResponseRedirects('/settings/catalog?run=run-1&task=sylius:dataset:build');
        $call = FakeCastorApi::calls('sylius:dataset:build')[0];
        static::assertSame('start', $call['action']);
        static::assertSame(['source' => 'ai', 'name' => 'Tea House', 'description' => 'Loose leaf teas and tea ware', 'url' => '', 'limit' => 12], $call['payload']);
    }

    public function testItImportsAnExistingWebsite(): void
    {
        $client = static::createAdminClient();
        $client->request('GET', '/catalog/new/existing');
        self::assertSelectorTextContains('main', 'Only a site you own');

        $client->submitForm('Build dataset', ['dataset_existing[name]' => 'My shop', 'dataset_existing[url]' => 'https://shop.example.com']);

        self::assertResponseRedirects();
        $payload = FakeCastorApi::calls('sylius:dataset:build')[0]['payload'];
        static::assertSame(['existing', 'https://shop.example.com', 100], [$payload['source'], $payload['url'], $payload['limit']]);
    }

    public function testABuildWaitsForTheRunHoldingItsLock(): void
    {
        FakeCastorApi::conflict('sylius:dataset:build', 'sylius:dataset:build', ['name' => 'Other']);
        $client = static::createAdminClient();
        $client->request('GET', '/catalog/new/ai');
        $client->submitForm('Build dataset', ['dataset_ai[name]' => 'Tea House', 'dataset_ai[description]' => 'Teas']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form[name="dataset_ai"]', '"Build dataset" is still running.');
    }

    public function testItCreatesAnEmptyDataset(): void
    {
        FakeCastorApi::output('sylius:dataset:create', "\n [OK] Dataset tea-house created.\n\ntea-house\n");
        $client = static::createAdminClient();
        $client->request('GET', '/catalog/new/manual');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select[name="dataset_settings[theme]"] option[value="drive"]');

        $client->submitForm('Create dataset', [
            'dataset_settings[name]' => 'Tea House',
            'dataset_settings[description]' => 'Loose leaf teas',
            'dataset_settings[theme]' => 'drive',
        ]);

        self::assertResponseRedirects('/catalog/tea-house');
        static::assertSame(['name' => 'Tea House', 'description' => 'Loose leaf teas', 'theme' => 'drive'], FakeCastorApi::calls('sylius:dataset:create')[0]['payload']);
    }

    public function testItShowsTheErrorOfTheTaskOnTheForm(): void
    {
        FakeCastorApi::fail('sylius:dataset:create', 'A dataset "my-catalog" already exists: choose another name.');
        $client = static::createAdminClient();
        $client->request('GET', '/catalog/new/manual');
        $client->submitForm('Create dataset', ['dataset_settings[name]' => 'My catalog']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form[name="dataset_settings"]', 'A dataset "my-catalog" already exists');
        self::assertInputValueSame('dataset_settings[name]', 'My catalog');
    }

    public function testADatasetBuiltInTheProjectCanBeEdited(): void
    {
        $crawler = static::createAdminClient()->request('GET', '/catalog/my-catalog');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/catalog/my-catalog/edit"]');
        self::assertSelectorExists('a[href="/catalog/my-catalog/products/new"]');
        self::assertSelectorExists('a[href="/catalog/my-catalog/collections/new"]');
        self::assertSelectorExists('a[href="/catalog/my-catalog/collections/1/edit"]');
        static::assertSame(
            ['/catalog/my-catalog/products/0/edit', '/catalog/my-catalog/products/1/edit', '/catalog/my-catalog/products/2/edit'],
            $crawler->filter('main ul.grid > li a[href*="/products/"]')->each(static fn(Crawler $link): string => (string) $link->attr('href')),
        );
        self::assertSelectorTextContains('main', 'Written by hand');
    }

    public function testAReadyMadeDatasetCannotBeEdited(): void
    {
        $client = static::createAdminClient();
        $crawler = $client->request('GET', '/catalog/teahouse');

        static::assertCount(0, $crawler->filter('a[href^="/catalog/teahouse/"]'));

        $client->request('GET', '/catalog/teahouse/products/0/edit');
        self::assertResponseStatusCodeSame(403);
    }

    public function testItEditsAProductAndUploadsItsImage(): void
    {
        $client = static::createAdminClient();
        $client->request('GET', '/catalog/my-catalog/products/1/edit');

        self::assertResponseIsSuccessful();
        self::assertInputValueSame('dataset_product[title]', 'Green mug');
        self::assertInputValueSame('dataset_product[price]', '13.00');
        self::assertSelectorExists('select[name="dataset_product[collection]"] option[value="Mugs"][selected]');
        self::assertSelectorExists('img[src="/catalog/my-catalog/media/green_mug.webp"]');
        self::assertSelectorExists('form[action^="/catalog/my-catalog/products/1/delete"]');

        $client->submitForm('Save', [
            'dataset_product[title]' => 'Sea green mug',
            'dataset_product[collection]' => 'Plates',
            'dataset_product[price]' => '14.5',
            'dataset_product[image]' => $this->image('mug.webp'),
        ]);

        self::assertResponseRedirects('/catalog/my-catalog');
        $payload = FakeCastorApi::calls('sylius:dataset:product')[0]['payload'];
        static::assertSame(1, $payload['index']);
        static::assertSame('Green mug', $payload['expected']);
        static::assertSame('Sea green mug', $payload['title']);
        static::assertSame('Plates', $payload['collection']);
        static::assertSame('14.5', $payload['price']);
        static::assertMatchesRegularExpression('/^[0-9a-f]{24}\.webp$/', $payload['image']);
        static::assertFileExists(self::UPLOADS . '/' . $payload['image']);
    }

    public function testANewProductNeedsAnImage(): void
    {
        $client = static::createAdminClient();
        $client->request('GET', '/catalog/my-catalog/products/new?collection=Plates');
        self::assertSelectorExists('select[name="dataset_product[collection]"] option[value="Plates"][selected]');

        $client->submitForm('Add product', ['dataset_product[title]' => 'Soup bowl', 'dataset_product[price]' => '9']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form[name="dataset_product"]', 'Every product needs an image.');
        static::assertSame([], FakeCastorApi::calls('sylius:dataset:product'));

        $client->submitForm('Add, then another', [
            'dataset_product[title]' => 'Soup bowl',
            'dataset_product[price]' => '9',
            'dataset_product[image]' => $this->image('bowl.webp'),
        ]);
        self::assertResponseRedirects('/catalog/my-catalog/products/new?collection=Plates');
        static::assertArrayNotHasKey('index', FakeCastorApi::calls('sylius:dataset:product')[0]['payload']);
    }

    public function testAnUploadThatIsNotAnImageIsRefused(): void
    {
        file_put_contents($this->temporary . '/fake.webp', 'not an image');
        $client = static::createAdminClient();
        $client->request('GET', '/catalog/my-catalog/products/0/edit');
        $client->submitForm('Save', ['dataset_product[image]' => new UploadedFile($this->temporary . '/fake.webp', 'fake.webp', 'image/webp', test: true)]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form[name="dataset_product"]', 'The image must be a JPEG, PNG, GIF, WebP or AVIF file.');
        static::assertSame([], FakeCastorApi::calls('sylius:dataset:product'));
    }

    public function testItRemovesAProduct(): void
    {
        $client = static::createAdminClient();
        $crawler = $client->request('GET', '/catalog/my-catalog/products/2/edit');
        $client->submit($crawler->filter('form[action^="/catalog/my-catalog/products/2/delete"]')->form());

        self::assertResponseRedirects('/catalog/my-catalog');
        static::assertSame(['dataset' => 'my-catalog', 'index' => 2, 'expected' => 'Dinner plate', 'remove' => true], FakeCastorApi::calls('sylius:dataset:product')[0]['payload']);
    }

    public function testItAddsAndEditsCollections(): void
    {
        $client = static::createAdminClient();
        $client->request('GET', '/catalog/my-catalog/collections/new');
        $client->submitForm('Add collection', ['dataset_collection[name]' => 'Espresso cups', 'dataset_collection[parent]' => 'Mugs']);

        self::assertResponseRedirects('/catalog/my-catalog');
        static::assertSame(['dataset' => 'my-catalog', 'name' => 'Espresso cups', 'parent' => 'Mugs', 'description' => ''], FakeCastorApi::calls('sylius:dataset:collection')[0]['payload']);

        // A collection cannot be its own parent.
        $crawler = $client->request('GET', '/catalog/my-catalog/collections/0/edit');
        static::assertSame(['', 'Plates'], $crawler->filter('select[name="dataset_collection[parent]"] option')->each(static fn(Crawler $option): string => (string) $option->attr('value')));
        self::assertSelectorTextContains('main', 'Its products are kept, without a collection.');

        $client->submitForm('Save', ['dataset_collection[name]' => 'Cups']);
        static::assertSame(['dataset' => 'my-catalog', 'index' => 0, 'expected' => 'Mugs', 'name' => 'Cups', 'parent' => '', 'description' => ''], FakeCastorApi::calls('sylius:dataset:collection')[1]['payload']);
    }

    public function testItEditsAndDeletesADataset(): void
    {
        $client = static::createAdminClient();
        $crawler = $client->request('GET', '/catalog/my-catalog/edit');
        self::assertInputValueSame('dataset_settings[name]', 'My catalog');
        $delete = $crawler->filter('form[action="/catalog/my-catalog/delete"]')->form();

        $client->submitForm('Save', ['dataset_settings[name]' => 'Our catalog']);
        self::assertResponseRedirects('/catalog/my-catalog');
        static::assertSame('Our catalog', FakeCastorApi::calls('sylius:dataset:update')[0]['payload']['name']);

        $client->submit($delete);
        self::assertResponseRedirects('/settings/catalog');
        static::assertSame(['dataset' => 'my-catalog'], FakeCastorApi::calls('sylius:dataset:delete')[0]['payload']);
    }

    public function testItImportsAZip(): void
    {
        $zip = new \ZipArchive();
        $zip->open($this->temporary . '/tea.zip', \ZipArchive::CREATE);
        $zip->addFromString('tea/products.yaml', "products: []\n");
        $zip->close();
        FakeCastorApi::output('sylius:dataset:import', "\n [OK] Dataset tea imported.\n\ntea\n");

        $client = static::createAdminClient();
        $client->request('GET', '/catalog/new/import');
        $client->submitForm('Import', [
            'dataset_import[file]' => new UploadedFile($this->temporary . '/tea.zip', 'tea.zip', 'application/zip', test: true),
            'dataset_import[replace]' => true,
        ]);

        self::assertResponseRedirects('/catalog/tea');
        $payload = FakeCastorApi::calls('sylius:dataset:import')[0]['payload'];
        static::assertMatchesRegularExpression('/^[0-9a-f]{24}\.zip$/', $payload['file']);
        static::assertSame('', $payload['dataset']);
        static::assertTrue($payload['replace']);
    }

    public function testAFailedImportRemovesItsUpload(): void
    {
        $zip = new \ZipArchive();
        $zip->open($this->temporary . '/empty.zip', \ZipArchive::CREATE);
        $zip->addFromString('readme.md', 'nothing');
        $zip->close();
        FakeCastorApi::fail('sylius:dataset:import', 'The zip holds no products.yaml.');

        $client = static::createAdminClient();
        $client->request('GET', '/catalog/new/import');
        $client->submitForm('Import', ['dataset_import[file]' => new UploadedFile($this->temporary . '/empty.zip', 'empty.zip', 'application/zip', test: true)]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form[name="dataset_import"]', 'The zip holds no products.yaml.');
        // An unchecked flag is not sent: castor-api would pass --no-replace.
        static::assertArrayNotHasKey('replace', FakeCastorApi::calls('sylius:dataset:import')[0]['payload']);
        static::assertSame([], glob(self::UPLOADS . '/*') ?: []);
    }

    private function image(string $name): UploadedFile
    {
        copy(Kernel::FIXTURES_DIR . '/datasets/my-catalog/media/blue_mug.webp', $this->temporary . '/' . $name);

        return new UploadedFile($this->temporary . '/' . $name, $name, 'image/webp', test: true);
    }
}
