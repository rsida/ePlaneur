<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Living style guide of the design toolkit, available in the dev environment only.
 */
final class ToolkitController extends AbstractController
{
    #[Route('/_toolkit', name: 'app_toolkit', methods: ['GET'], env: 'dev')]
    public function index(): Response
    {
        return $this->render('toolkit/index.html.twig');
    }
}
