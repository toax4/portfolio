<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DefaultController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('index.html.twig');
    }

    #[Route('/projets', name: 'projects_index', methods: ['GET'])]
    public function projects(): Response
    {
        return $this->render('projects.html.twig');
    }

    #[Route('/projets/gestion-de-stock', name: 'project_show', methods: ['GET'])]
    public function project(): Response
    {
        return $this->render('project.html.twig');
    }

    #[Route('/experiences/developpeur-full-stack', name: 'experience_show', methods: ['GET'])]
    public function experience(): Response
    {
        return $this->render('experience.html.twig');
    }
}
