<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AboutmeController extends AbstractController
{
    #[Route('/about-me', name: 'app_aboutme')]
    public function index(): Response
    {
        return $this->render('aboutme/index.html.twig', [
            'controller_name' => 'AboutmeController',
        ]);
    }
}
