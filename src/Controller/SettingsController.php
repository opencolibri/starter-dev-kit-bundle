<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Controller;

use OpenColibri\Starter\DevKitBundle\Settings\SettingsPages;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A settings page of the shop (see SettingsPages): the hook "opencolibri_dev_kit.<page>.index" and its cards.
 */
final class SettingsController extends AbstractController
{
    public function __construct(
        private readonly SettingsPages $pages,
    ) {}

    #[Route('/settings/{page}', name: 'opencolibri_dev_kit_settings', requirements: ['page' => '[a-z0-9_]+'], methods: ['GET'])]
    public function show(string $page): Response
    {
        $settings = $this->pages->get($page) ?? throw $this->createNotFoundException(\sprintf('No "%s" settings page.', $page));

        return $this->render('@OpenColibriStarterDevKit/settings/show.html.twig', [
            'page_name' => $page,
            'page' => ['title' => $settings['title'], 'subheader' => $settings['subheader'], 'icon' => $settings['icon']],
        ]);
    }
}
