<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Controller;

use OpenColibri\Starter\DevKitBundle\Castor\CastorApiClient;
use OpenColibri\Starter\DevKitBundle\Castor\RunConflictException;
use OpenColibri\Starter\DevKitBundle\Castor\RunDescription;
use OpenColibri\Starter\DevKitBundle\Dataset\DatasetCatalog;
use OpenColibri\Starter\DevKitBundle\Dataset\DatasetCatalogReader;
use OpenColibri\Starter\DevKitBundle\Form\DatasetAiType;
use OpenColibri\Starter\DevKitBundle\Form\DatasetCollectionType;
use OpenColibri\Starter\DevKitBundle\Form\DatasetExistingType;
use OpenColibri\Starter\DevKitBundle\Form\DatasetImportType;
use OpenColibri\Starter\DevKitBundle\Form\DatasetProductType;
use OpenColibri\Starter\DevKitBundle\Form\DatasetSettingsType;
use OpenColibri\Starter\DevKitBundle\Import\DatasetUploadStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The datasets of the project: built by the AI (from a description, or from an existing website), written by hand (an
 * empty dataset filled with collections and products), or imported from a zip. The changes go through the
 * sylius:dataset:build, :create, :update, :collection, :product, :import and :delete tasks of castor-api; the pages
 * read the catalog from the files of the project.
 */
final class DatasetEditController extends AbstractController
{
    private const string SLUG = '[a-z0-9][a-z0-9-]*';

    public function __construct(
        private readonly CastorApiClient $castorApi,
        private readonly DatasetCatalogReader $catalogReader,
        private readonly DatasetUploadStorage $uploads,
    ) {}

    #[Route('/catalog/new/manual', name: 'opencolibri_dev_kit_dataset_create')]
    public function create(Request $request): Response
    {
        $form = $this->createForm(DatasetSettingsType::class, null, ['themes' => $this->themes()]);
        $form->handleRequest($request);
        $page = [
            'title' => 'opencolibri_dev_kit.ui.new_dataset_manual',
            'subheader' => 'opencolibri_dev_kit.ui.new_dataset_manual_subheader',
            'icon' => 'lucide:pencil-ruler',
            'submit' => 'opencolibri_dev_kit.ui.create_dataset',
            'submit_icon' => 'lucide:plus',
        ];

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{name: string, description: ?string, theme?: ?string} $data */
            $data = $form->getData();
            $result = $this->runTask($form, 'sylius:dataset:create', [
                'name' => $data['name'],
                'description' => (string) $data['description'],
                'theme' => (string) ($data['theme'] ?? ''),
            ]);

            if (null !== $result) {
                $this->addFlash('success', 'Dataset created: add its collections, then its products.');

                return $this->redirectToDataset(self::lastLine($result));
            }

            return $this->renderForm($form, $page, failed: true);
        }

        return $this->renderForm($form, $page);
    }

    #[Route('/catalog/new/import', name: 'opencolibri_dev_kit_dataset_import')]
    public function import(Request $request): Response
    {
        $form = $this->createForm(DatasetImportType::class);
        $form->handleRequest($request);
        $page = [
            'title' => 'opencolibri_dev_kit.ui.import_dataset',
            'subheader' => 'opencolibri_dev_kit.ui.import_dataset_subheader',
            'icon' => 'lucide:file-archive',
            'submit' => 'opencolibri_dev_kit.ui.import',
            'submit_icon' => 'lucide:upload',
        ];

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{file: UploadedFile, dataset: ?string, replace: bool} $data */
            $data = $form->getData();
            $upload = null;

            try {
                $upload = $this->uploads->saveZip($data['file']);
                // A flag is only sent when set: castor-api turns false into a --no-replace option the task lacks.
                $result = $this->castorApi->run('sylius:dataset:import', [
                    'file' => $upload,
                    'dataset' => (string) $data['dataset'],
                ] + ($data['replace'] ? ['replace' => true] : []));
            } catch (\InvalidArgumentException|\RuntimeException $exception) {
                if (null !== $upload) {
                    $this->uploads->remove($upload);
                }

                $form->addError(new FormError($exception->getMessage()));

                return $this->renderForm($form, $page, failed: true);
            }
            $this->addFlash('success', 'Dataset imported.');

            return $this->redirectToDataset(self::lastLine($result));
        }

        return $this->renderForm($form, $page);
    }

    /**
     * Builds a dataset with the AI: a run of a few minutes, followed in the tasks panel of the catalog page, which
     * reloads with the new dataset once it finishes.
     */
    #[Route('/catalog/new/{source}', name: 'opencolibri_dev_kit_dataset_build', requirements: ['source' => 'ai|existing'])]
    public function build(Request $request, string $source): Response
    {
        $form = $this->createForm('ai' === $source ? DatasetAiType::class : DatasetExistingType::class);
        $form->handleRequest($request);
        $page = 'ai' === $source ? [
            'title' => 'opencolibri_dev_kit.ui.new_dataset_ai',
            'subheader' => 'opencolibri_dev_kit.ui.new_dataset_ai_subheader',
            'icon' => 'lucide:sparkles',
        ] : [
            'title' => 'opencolibri_dev_kit.ui.new_dataset_existing',
            'subheader' => 'opencolibri_dev_kit.ui.new_dataset_existing_subheader',
            'icon' => 'lucide:globe',
        ];
        $page += ['submit' => 'opencolibri_dev_kit.ui.build_dataset', 'submit_icon' => 'lucide:hammer'];

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{name: string, description: ?string, url: ?string, limit: int, images: bool} $data */
            $data = $form->getData();

            try {
                // A flag is only sent when set: castor-api turns false into a --no-skip-images option the task lacks.
                $started = $this->castorApi->start('sylius:dataset:build', [
                    'source' => $source,
                    'name' => $data['name'],
                    'description' => (string) $data['description'],
                    'url' => (string) $data['url'],
                    'limit' => $data['limit'],
                ] + ($data['images'] ? [] : ['skip-images' => true]));
            } catch (RunConflictException $exception) {
                $form->addError(new FormError(\sprintf(
                    '"%s" is still running. Wait for it to finish before building a dataset.',
                    RunDescription::label((string) ($exception->conflict['task'] ?? '')),
                )));

                return $this->renderForm($form, $page, failed: true);
            } catch (\RuntimeException $exception) {
                $form->addError(new FormError($exception->getMessage()));

                return $this->renderForm($form, $page, failed: true);
            }

            return $this->redirectToRoute('opencolibri_dev_kit_settings', ['page' => 'catalog', 'run' => $started['id'], 'task' => $started['task']]);
        }

        return $this->renderForm($form, $page);
    }

    #[Route('/catalog/{slug}/edit', name: 'opencolibri_dev_kit_dataset_edit', requirements: ['slug' => self::SLUG])]
    public function edit(Request $request, string $slug): Response
    {
        $dataset = $this->editableDataset($slug);
        $form = $this->createForm(DatasetSettingsType::class, [
            'name' => $dataset['name'],
            'description' => $dataset['description'],
            'theme' => $dataset['theme'],
        ], ['themes' => $this->themes($dataset['theme'])]);
        $form->handleRequest($request);
        $page = [
            'dataset' => $dataset,
            'title' => 'opencolibri_dev_kit.ui.edit_dataset',
            'icon' => 'lucide:pencil',
            'submit' => 'opencolibri_dev_kit.ui.save',
            'delete' => [
                'id' => 'dataset_delete_' . $slug,
                'path' => $this->generateUrl('opencolibri_dev_kit_dataset_delete', ['slug' => $slug]),
                'label' => 'opencolibri_dev_kit.ui.delete_dataset',
                'help' => 'opencolibri_dev_kit.ui.delete_dataset_help',
            ],
        ];

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{name: string, description: ?string, theme?: ?string} $data */
            $data = $form->getData();

            if (null !== $this->runTask($form, 'sylius:dataset:update', [
                'dataset' => $slug,
                'name' => $data['name'],
                'description' => (string) $data['description'],
                'theme' => (string) ($data['theme'] ?? ''),
            ])) {
                $this->addFlash('success', 'Dataset updated.');

                return $this->redirectToDataset($slug);
            }

            return $this->renderForm($form, $page, failed: true);
        }

        return $this->renderForm($form, $page);
    }

    /**
     * Downloads or generates the images the products and collections lack (their image URL or prompt), in the tasks
     * panel of the dataset page.
     */
    #[Route('/catalog/{slug}/images', name: 'opencolibri_dev_kit_dataset_images', requirements: ['slug' => self::SLUG], methods: ['POST'])]
    public function images(Request $request, string $slug): Response
    {
        $this->editableDataset($slug);

        if (!$this->isCsrfTokenValid('dataset_images_' . $slug, $request->request->getString('_token'))) {
            $this->addFlash('error', 'Invalid form submission.');

            return $this->redirectToDataset($slug);
        }

        try {
            $started = $this->castorApi->start('sylius:dataset:images', ['dataset' => $slug]);
        } catch (RunConflictException $exception) {
            $this->addFlash('error', \sprintf('"%s" is still running on this dataset: wait for it to finish.', RunDescription::label((string) ($exception->conflict['task'] ?? ''))));

            return $this->redirectToDataset($slug);
        } catch (\RuntimeException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToDataset($slug);
        }

        return $this->redirectToRoute('opencolibri_dev_kit_dataset_show', ['slug' => $slug, 'run' => $started['id'], 'task' => $started['task']]);
    }

    #[Route('/catalog/{slug}/delete', name: 'opencolibri_dev_kit_dataset_delete', requirements: ['slug' => self::SLUG], methods: ['POST', 'DELETE'])]
    public function delete(Request $request, string $slug): Response
    {
        $this->editableDataset($slug);

        // Sent by the sylius_shadcn_admin_ui:delete_modal component.
        if (!$this->isCsrfTokenValid('dataset_delete_' . $slug, $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'Invalid form submission.');

            return $this->redirectToDataset($slug);
        }

        try {
            $this->castorApi->run('sylius:dataset:delete', ['dataset' => $slug]);
            $this->addFlash('success', \sprintf('Dataset "%s" deleted. A catalog loaded from it stays in the shop.', $slug));
        } catch (\RuntimeException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToDataset($slug);
        }

        return $this->redirectToRoute('opencolibri_dev_kit_settings', ['page' => 'catalog']);
    }

    #[Route('/catalog/{slug}/collections/new', name: 'opencolibri_dev_kit_dataset_collection_new', requirements: ['slug' => self::SLUG])]
    #[Route('/catalog/{slug}/collections/{index}/edit', name: 'opencolibri_dev_kit_dataset_collection_edit', requirements: ['slug' => self::SLUG, 'index' => '\d+'])]
    public function collection(Request $request, string $slug, ?int $index = null): Response
    {
        $dataset = $this->editableDataset($slug);
        $catalog = $this->catalog($slug);
        $collection = null !== $index ? ($catalog->collection($index) ?? throw $this->createNotFoundException()) : null;

        // A parent is a top-level collection, other than this one.
        $parents = [];

        foreach ($catalog->collections as $item) {
            if (null === $item['parent'] && $item['index'] !== $index) {
                $parents[$item['name']] = $item['key'];
            }
        }

        $form = $this->createForm(DatasetCollectionType::class, null !== $collection ? [
            'name' => $collection['name'],
            'parent' => $collection['parent'],
            'description' => $collection['description'],
        ] : null, ['parents' => $parents, 'has_image' => null !== ($collection['image'] ?? null)]);
        $form->handleRequest($request);
        $page = [
            'dataset' => $dataset,
            'title' => null !== $collection ? 'opencolibri_dev_kit.ui.edit_collection' : 'opencolibri_dev_kit.ui.new_collection',
            'icon' => 'lucide:folder-tree',
            'submit' => null !== $collection ? 'opencolibri_dev_kit.ui.save' : 'opencolibri_dev_kit.ui.add_collection',
            'submit_icon' => null !== $collection ? 'lucide:check' : 'lucide:plus',
            'image' => $collection['image'] ?? null,
            'delete' => null !== $collection ? [
                'id' => 'dataset_collection_delete_' . $slug . '_' . $index,
                'path' => $this->generateUrl('opencolibri_dev_kit_dataset_collection_delete', ['slug' => $slug, 'index' => $index, 'expected' => $collection['name']]),
                'label' => 'opencolibri_dev_kit.ui.delete',
                'help' => 'opencolibri_dev_kit.ui.delete_collection_help',
            ] : null,
        ];

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{name: string, parent: ?string, description: ?string, image: ?UploadedFile} $data */
            $data = $form->getData();

            if (null !== $this->runTask($form, 'sylius:dataset:collection', array_filter([
                'dataset' => $slug,
                'index' => $index,
                'expected' => $collection['name'] ?? null,
                'name' => $data['name'],
                'parent' => (string) $data['parent'],
                'description' => (string) $data['description'],
            ], static fn(mixed $value): bool => null !== $value), $data['image'])) {
                $this->addFlash('success', \sprintf('Collection "%s" saved.', $data['name']));

                return $this->redirectToDataset($slug);
            }

            return $this->renderForm($form, $page, failed: true);
        }

        return $this->renderForm($form, $page);
    }

    #[Route('/catalog/{slug}/collections/{index}/delete', name: 'opencolibri_dev_kit_dataset_collection_delete', requirements: ['slug' => self::SLUG, 'index' => '\d+'], methods: ['POST', 'DELETE'])]
    public function deleteCollection(Request $request, string $slug, int $index): Response
    {
        return $this->remove($request, $slug, 'sylius:dataset:collection', 'dataset_collection_delete_' . $slug . '_' . $index, $index, 'Collection removed: its products are kept, without a collection.');
    }

    #[Route('/catalog/{slug}/products/new', name: 'opencolibri_dev_kit_dataset_product_new', requirements: ['slug' => self::SLUG])]
    #[Route('/catalog/{slug}/products/{index}/edit', name: 'opencolibri_dev_kit_dataset_product_edit', requirements: ['slug' => self::SLUG, 'index' => '\d+'])]
    public function product(Request $request, string $slug, ?int $index = null): Response
    {
        $dataset = $this->editableDataset($slug);
        $catalog = $this->catalog($slug);
        $product = null !== $index ? ($catalog->product($index) ?? throw $this->createNotFoundException()) : null;
        $collection = $request->query->getString('collection');
        $choices = $catalog->collectionChoices();

        $form = $this->createForm(DatasetProductType::class, null !== $product ? [
            'title' => $product['title'],
            'collection' => $product['collectionKey'],
            'price' => $product['price'],
            'description' => $product['description'],
        ] : ['collection' => \in_array($collection, $choices, true) ? $collection : null], [
            'collections' => $choices,
            'has_image' => null !== ($product['image'] ?? null),
            'is_new' => null === $product,
        ]);
        $form->handleRequest($request);
        $page = [
            'dataset' => $dataset,
            'title' => null !== $product ? 'opencolibri_dev_kit.ui.edit_product' : 'opencolibri_dev_kit.ui.new_product',
            'icon' => 'lucide:package',
            'submit' => null !== $product ? 'opencolibri_dev_kit.ui.save' : 'opencolibri_dev_kit.ui.add_product',
            'submit_icon' => null !== $product ? 'lucide:check' : 'lucide:plus',
            'add_another' => null === $product,
            'image' => $product['image'] ?? null,
            'delete' => null !== $product ? [
                'id' => 'dataset_product_delete_' . $slug . '_' . $index,
                'path' => $this->generateUrl('opencolibri_dev_kit_dataset_product_delete', ['slug' => $slug, 'index' => $index, 'expected' => $product['title']]),
                'label' => 'opencolibri_dev_kit.ui.delete',
                'help' => null,
            ] : null,
        ];

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{title: string, collection: ?string, price: float|int|null, description: ?string, image: ?UploadedFile} $data */
            $data = $form->getData();

            if (null !== $this->runTask($form, 'sylius:dataset:product', array_filter([
                'dataset' => $slug,
                'index' => $index,
                'expected' => $product['title'] ?? null,
                'title' => $data['title'],
                'collection' => (string) $data['collection'],
                'price' => null !== $data['price'] ? (string) $data['price'] : '',
                'description' => (string) $data['description'],
            ], static fn(mixed $value): bool => null !== $value), $data['image'])) {
                $this->addFlash('success', \sprintf('Product "%s" saved.', $data['title']));

                if (null === $product && $request->request->has('add_another')) {
                    return $this->redirectToRoute('opencolibri_dev_kit_dataset_product_new', ['slug' => $slug, 'collection' => (string) $data['collection']]);
                }

                return $this->redirectToDataset($slug);
            }

            return $this->renderForm($form, $page, failed: true);
        }

        return $this->renderForm($form, $page);
    }

    #[Route('/catalog/{slug}/products/{index}/delete', name: 'opencolibri_dev_kit_dataset_product_delete', requirements: ['slug' => self::SLUG, 'index' => '\d+'], methods: ['POST', 'DELETE'])]
    public function deleteProduct(Request $request, string $slug, int $index): Response
    {
        return $this->remove($request, $slug, 'sylius:dataset:product', 'dataset_product_delete_' . $slug . '_' . $index, $index, 'Product removed.');
    }

    private function remove(Request $request, string $slug, string $task, string $tokenId, int $index, string $message): Response
    {
        $this->editableDataset($slug);

        // Sent by the sylius_shadcn_admin_ui:delete_modal component.
        if (!$this->isCsrfTokenValid($tokenId, $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'Invalid form submission.');

            return $this->redirectToDataset($slug);
        }

        try {
            $this->castorApi->run($task, ['dataset' => $slug, 'index' => $index, 'expected' => $request->query->getString('expected'), 'remove' => true]);
            $this->addFlash('success', $message);
        } catch (\RuntimeException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToDataset($slug);
    }

    /**
     * Runs a task with the uploaded image, if any: a failure becomes an error of the form.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|null the result of the task, null when it failed
     */
    private function runTask(FormInterface $form, string $task, array $payload, ?UploadedFile $image = null): ?array
    {
        $upload = null;

        try {
            if (null !== $image) {
                $payload['image'] = $upload = $this->uploads->saveImage($image);
            }

            $result = $this->castorApi->run($task, $payload);
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            if (null !== $upload) {
                $this->uploads->remove($upload);
            }

            $form->addError(new FormError($exception->getMessage()));

            return null;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $page title, subheader, icon, submit, submit_icon, add_another, dataset, image, delete
     */
    private function renderForm(FormInterface $form, array $page, bool $failed = false): Response
    {
        return $this->render('@OpenColibriStarterDevKit/dataset/form.html.twig', ['form' => $form, ...$page], $failed ? new Response(status: 422) : null);
    }

    private function redirectToDataset(?string $slug): Response
    {
        return null !== $slug
            ? $this->redirectToRoute('opencolibri_dev_kit_dataset_show', ['slug' => $slug])
            : $this->redirectToRoute('opencolibri_dev_kit_settings', ['page' => 'catalog']);
    }

    /**
     * A dataset built in the project, as dev-kit:catalog lists it: the ready-made ones are read only.
     *
     * @return array{slug: string, name: string, description: string|null, theme: string|null}
     */
    private function editableDataset(string $slug): array
    {
        try {
            $datasets = $this->castorApi->catalog()['datasets'];
        } catch (\RuntimeException $exception) {
            throw new \RuntimeException(\sprintf('castor-api cannot list the datasets: %s', $exception->getMessage()), 0, $exception);
        }

        foreach ($datasets as $dataset) {
            if (($dataset['slug'] ?? null) !== $slug) {
                continue;
            }

            if (false !== ($dataset['builtIn'] ?? true)) {
                throw $this->createAccessDeniedException(\sprintf('"%s" is a ready-made dataset: import its zip under another name to change a copy.', $slug));
            }

            $string = static fn(mixed $value): ?string => \is_string($value) && '' !== $value ? $value : null;

            return [
                'slug' => $slug,
                'name' => $string($dataset['name'] ?? null) ?? $slug,
                'description' => $string($dataset['description'] ?? null),
                'theme' => $string($dataset['theme'] ?? null),
            ];
        }

        throw $this->createNotFoundException(\sprintf('No "%s" dataset.', $slug));
    }

    private function catalog(string $slug): DatasetCatalog
    {
        return $this->catalogReader->read($slug) ?? throw $this->createNotFoundException(\sprintf('The files of the "%s" dataset are not readable.', $slug));
    }

    /**
     * The themes a dataset can suggest, or null (no field) when castor-api cannot list them.
     *
     * @return list<string>|null
     */
    private function themes(?string $current = null): ?array
    {
        try {
            $themes = array_values(array_filter(array_map(static fn(array $theme): mixed => $theme['name'] ?? null, $this->castorApi->catalog()['themes']), \is_string(...)));
        } catch (\RuntimeException) {
            return null;
        }

        // Keep the theme of the dataset even when it is not installed anymore.
        return null !== $current && !\in_array($current, $themes, true) ? [...$themes, $current] : $themes;
    }

    /**
     * The slug the create and import tasks print last.
     *
     * @param array<string, mixed> $result
     */
    private static function lastLine(array $result): ?string
    {
        $lines = array_filter(array_map(trim(...), explode("\n", (string) ($result['stdout'] ?? ''))));
        $last = end($lines);

        return \is_string($last) && preg_match('/^' . self::SLUG . '$/', $last) ? $last : null;
    }
}
