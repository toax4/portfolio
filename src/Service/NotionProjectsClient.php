<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class NotionProjectsClient
{
    private const NOTION_VERSION = '2022-06-28';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly SluggerInterface $slugger,
        #[Autowire(env: 'NOTION_API_KEY')]
        private readonly string $apiKey,
        #[Autowire(env: 'NOTION_DB_PROJETS')]
        private readonly string $databaseId,
        #[Autowire(env: 'NOTION_DB_TECHNOS')]
        private readonly string $technologiesDatabaseId,
    ) {
    }

    /**
     * @return list<array{title: string, types: list<string>, types_slug: list<string>, technologies: list<string>, technology_slugs: list<string>, show: bool, show_showcase: bool, href: ?string}>
     */
    public function getProjects(): array
    {
        try {
            $response = $this->httpClient->request('POST', "https://api.notion.com/v1/databases/{$this->databaseId}/query", [
                'auth_bearer' => $this->apiKey,
                'headers' => [
                    'Notion-Version' => self::NOTION_VERSION,
                ],
                'json' => [
                    'filter' => [
                        'property' => 'Afficher ?',
                        'checkbox' => ['equals' => true],
                    ],
                    'sorts' => [
                        ['property' => 'Date de réalisation', 'direction' => 'descending'],
                    ],
                ],
            ]);

            $pages = $response->toArray()['results'];
        } catch (ExceptionInterface $exception) {
            $this->logger->error('Impossible de récupérer les projets Notion.', ['exception' => $exception]);

            return [];
        }

        $technologyNames = $this->getTechnologies();

        return array_map(fn (array $page): array => $this->mapPage($page, $technologyNames), $pages);
    }

    public function getTechnologies(): array
    {
        $cursor = null;

        try {
            $response = $this->httpClient->request('POST', "https://api.notion.com/v1/databases/{$this->technologiesDatabaseId}/query", [
                'auth_bearer' => $this->apiKey,
                'headers' => [
                    'Notion-Version' => self::NOTION_VERSION,
                ],
            ]);

            $pages = $response->toArray()['results'];
        } catch (ExceptionInterface $exception) {
            $this->logger->error('Impossible de récupérer les projets Notion.', ['exception' => $exception]);

            return [];
        }

        $datas = [];
        foreach ($pages as $page) {
            $datas[$page['id']] = $this->mapTechnology($page);
        }

        return $datas;
        // return array_map(fn (array $page): array => $this->mapTechnology($page), $pages);
    }

    public function getPage($pageId): array
    {
        try {
            $response = $this->httpClient->request('GET', "https://api.notion.com/v1/pages/{$pageId}/", [
                'auth_bearer' => $this->apiKey,
                'headers' => [
                    'Notion-Version' => self::NOTION_VERSION,
                ],
            ]);

            $pages = $response->toArray();
        } catch (ExceptionInterface $exception) {
            $this->logger->error('Impossible de récupérer les projets Notion.', ['exception' => $exception]);

            return [];
        }

        $technologyNames = $this->getTechnologies();

        return $this->mapPage($pages, $technologyNames);
    }

    public function getPageContent($pageId): ?string
    {
        try {
            $response = $this->httpClient->request('GET', "https://api.notion.com/v1/pages/{$pageId}/markdown", [
                'auth_bearer' => $this->apiKey,
                'headers' => [
                    'Notion-Version' => self::NOTION_VERSION,
                ],
            ]);

            $pages = $response->toArray();
        } catch (ExceptionInterface $exception) {
            $this->logger->error('Impossible de récupérer les projets Notion.', ['exception' => $exception]);

            return null;
        }

        return $pages['markdown'] ?? null;
    }

    /**
     * @param array<string, mixed> $page
     * @param array<string, array> $technologyNames
     *
     * @return array{title: string, types: list<string>, types_slug: list<string>, technologies: list<string>, technology_slugs: list<string>, show: bool, show_showcase: bool, href: ?string}
     */
    private function mapPage(array $page, array $technologyNames): array
    {
        $properties = $page['properties'];

        $type = $properties['Type']['select']['name'] ?? null;
        if ('Défi' === $type) {
            $types = [$type];
        } else {
            $types = array_map(
                static fn (array $option): string => $option['name'],
                $properties['Type de projet']['multi_select'] ?? [],
            );
        }

        // dd($technologyNames);

        $technologies = array_values(array_filter(array_map(
            static fn (array $relation): ?string => $technologyNames[$relation['id']]['title'] ?? null,
            $properties['Technologies']['relation'] ?? [],
        )));

        $cover = null;
        if (null != $page['cover']) {
            $cover_type = $page['cover']['type'];
            $cover = $page['cover'][$cover_type]['url'];
        }

        return [
            'id' => $page['id'],
            'title' => $properties['Nom']['title'][0]['plain_text'] ?? '',
            'description' => $properties['Description']['rich_text'][0]['plain_text'] ?? '',
            'cover' => $cover,
            'types' => $types,
            'technologies' => $technologies,
            'show' => $properties['Afficher ?']['checkbox'] ?? false,
            'show_showcase' => $properties['Projet phare ?']['checkbox'] ?? false,
            'href_depot' => $properties['Lien dépot']['url'] ?? $properties['Lien dépot']['url'] ?? null,
            'href_demo' => $properties['Lien démo']['url'] ?? $properties['Lien démo']['url'] ?? null,
            'content' => '',
        ];
    }

    private function mapTechnology(array $page): array
    {
        $properties = $page['properties'];

        $icon = null;
        if (null != $page['icon']) {
            $icon_type = $page['icon']['type'];
            $icon = $page[$icon_type]['url'] ?? null;
        }

        return [
            'title' => $properties['Nom']['title'][0]['plain_text'] ?? '',
            'icon' => $icon,
            'rate' => $properties['Pourcentage de connaissance']['number'] ?? 0,
            'color' => $properties['Couleur']['rich_text'][0]['plain_text'] ?? '',
            'type' => $properties['Type']['select']['name'] ?? '',
            'show' => $properties['En vitrine ?']['checkbox'] ?? false,
            'position' => $properties['Position']['number'] ?? 99999999,
        ];
    }
}
