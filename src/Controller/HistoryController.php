<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The states dev-kit:apply brought the shop to (dev-kit:history), and the way back to each of them.
 */
final class HistoryController extends AbstractController
{
    #[Route('/history', name: 'opencolibri_dev_kit_history', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('@OpenColibriStarterDevKit/history/index.html.twig');
    }
}
