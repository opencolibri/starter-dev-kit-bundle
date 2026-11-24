<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Controller;

use OpenColibri\Starter\DevKitBundle\Dataset\DatasetCatalogReader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * What a dataset holds before it is loaded: its collections and products, with their images and prices, and the
 * button that loads it into the shop.
 */
final class DatasetController extends AbstractController
{
    /** The Sylius demo data (castor sylius:fixtures), next to the datasets. */
    public const string SYLIUS_FIXTURES = 'sylius';

    public function __construct(
        private readonly DatasetCatalogReader $catalogReader,
    ) {}

    #[Route('/catalog/{slug}', name: 'opencolibri_dev_kit_dataset_show', requirements: ['slug' => '[a-z0-9][a-z0-9_-]*'], methods: ['GET'])]
    public function show(Request $request, string $slug): Response
    {
        // The Sylius demo data have no files to read: their page only explains them and loads them.
        if (self::SYLIUS_FIXTURES === $slug) {
            return $this->render('@OpenColibriStarterDevKit/dataset/show.html.twig', ['slug' => $slug, 'catalog' => null, 'collection' => null]);
        }

        $catalog = $this->catalogReader->read($slug) ?? throw $this->createNotFoundException(\sprintf('No "%s" dataset.', $slug));
        $collection = $request->query->getString('collection');

        return $this->render('@OpenColibriStarterDevKit/dataset/show.html.twig', [
            'slug' => $slug,
            'catalog' => $catalog,
            'collection' => \in_array($collection, array_column($catalog->collections, 'name'), true) ? $collection : null,
        ]);
    }

    #[Route('/catalog/{slug}/media/{file}', name: 'opencolibri_dev_kit_dataset_media', requirements: ['slug' => '[a-z0-9][a-z0-9_-]*', 'file' => '[A-Za-z0-9][A-Za-z0-9._-]*'], methods: ['GET'])]
    public function media(string $slug, string $file): BinaryFileResponse
    {
        $path = $this->catalogReader->mediaPath($slug, $file) ?? throw $this->createNotFoundException();

        $response = new BinaryFileResponse($path, autoEtag: true, autoLastModified: true);
        $response->setPrivate();
        $response->setMaxAge(3600);

        return $response;
    }
}
