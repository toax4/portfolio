<?php

namespace App\Controller;

use App\Service\NotionProjectsClient;
use App\Utils\StringUtils;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DefaultController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(NotionProjectsClient $notionProjectsClient): Response
    {
        $technologies = array_values($notionProjectsClient->getTechnologies());

        $technologies = array_filter($technologies, static fn (array $technology): bool => $technology['show']);
        // Position croissant puis rate decroissant
        usort($technologies, static fn (array $a, array $b): int => [$a['position'], $b['rate']] <=> [$b['position'], $a['rate']]);

        $featuredProjects = array_values(array_filter(
            $notionProjectsClient->getProjects(),
            static fn (array $project): bool => $project['show_showcase'],
        ));

        return $this->render('index.html.twig', [
            'projects' => $featuredProjects,
            'technologies' => $technologies,
        ]);
    }

    #[Route('/projets', name: 'projects_index', methods: ['GET'])]
    public function projects(NotionProjectsClient $notionProjectsClient): Response
    {
        $projects = $notionProjectsClient->getProjects();

        // Filter options are derived from the projects themselves (slug => label)
        // so the buttons on the page always match what Notion actually contains.
        $filterTypes = [];
        $filterTechnologies = [];
        foreach ($projects as $project) {
            foreach ($project['types'] as $i => $type) {
                $filterTypes[StringUtils::slugify($type)] = $type;
            }
            foreach ($project['technologies'] as $i => $techno) {
                $filterTechnologies[StringUtils::slugify($techno)] = $techno;
            }
        }

        ksort($filterTypes);
        ksort($filterTechnologies);

        return $this->render('projects.html.twig', [
            'projects' => $projects,
            'filter_types' => $filterTypes,
            'filter_technologies' => $filterTechnologies,
        ]);
    }

    #[Route('/projets/{pageId}', name: 'project_show', methods: ['GET'])]
    public function project(NotionProjectsClient $notionProjectsClient, string $pageId): Response
    {
        // dd($pageId);
        $page = $notionProjectsClient->getPage($pageId);
        $content = $notionProjectsClient->getPageContent($pageId);

        // dd($page);

        return $this->render('project.html.twig', [
            'project' => $page,
            'content' => $content,
        ]);
    }

    #[Route('/experiences/developpeur-full-stack', name: 'experience_show', methods: ['GET'])]
    public function experience(): Response
    {
        return $this->render('experience.html.twig');
    }
}
