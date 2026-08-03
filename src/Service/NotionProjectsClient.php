<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class NotionProjectsClient
{
    private const NOTION_VERSION = '2022-06-28';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly CacheInterface $cache,
        private readonly SluggerInterface $slugger,
        #[Autowire(env: 'NOTION_API_KEY')]
        private readonly string $apiKey,
        #[Autowire(env: 'NOTION_DB_PROJETS')]
        private readonly string $databaseId,
        #[Autowire(env: 'NOTION_DB_TECHNOS')]
        private readonly string $technologiesDatabaseId,
        #[Autowire(env: 'NOTION_DB_SKILLS')]
        private readonly string $skillsDatabaseId,
        #[Autowire(env: 'NOTION_DB_EXPERIENCES')]
        private readonly string $experiencesDatabaseId,
        #[Autowire(env: 'NOTION_DB_FORMATIONS')]
        private readonly string $formationsDatabaseId,
    ) {
    }

    /**
     * @return list<array{title: string, types: list<string>, types_slug: list<string>, technologies: list<string>, technology_slugs: list<string>, show: bool, show_showcase: bool, href: ?string}>
     */
    public function getProjects(): array
    {
        return $this->cache->get('notion_featured_projects', function (ItemInterface $item): array {
            $item->expiresAfter(3600);

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
                $technologyNames = $this->getTechnologies();
                $skills = $this->getSkills();

                return array_map(fn (array $page): array => $this->mapPage($page, technologyNames: $technologyNames, skills: $skills), $pages);
            } catch (ExceptionInterface $exception) {
                $this->logger->error('Impossible de récupérer les projets Notion.', ['exception' => $exception]);

                return [];
            }
        });
    }

    public function getExperiences(): array
    {
        return $this->cache->get('notion_featured_experiences', function (ItemInterface $item): array {
            $item->expiresAfter(3600);

            try {
                $response = $this->httpClient->request('POST', "https://api.notion.com/v1/databases/{$this->experiencesDatabaseId}/query", [
                    'auth_bearer' => $this->apiKey,
                    'headers' => [
                        'Notion-Version' => self::NOTION_VERSION,
                    ],
                ]);

                $pages = $response->toArray()['results'];

                $skills = $this->getSkills();
                $technologies = $this->getTechnologies();

                return array_map(fn (array $page): array => $this->mapExperience($page, skills: $skills, technologies: $technologies), $pages);
            } catch (ExceptionInterface $exception) {
                $this->logger->error('Impossible de récupérer les projets Notion.', ['exception' => $exception]);

                return [];
            }
        });
    }

    public function getExperience($pageId): array
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

        $skills = $this->getSkills();
        $technologies = $this->getTechnologies();

        return $this->mapExperience($pages, skills: $skills, technologies: $technologies);
    }

    public function getExperienceContent($pageId): ?string
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

    public function getFormations(): array
    {
        return $this->cache->get('notion_featured_formations', function (ItemInterface $item): array {
            $item->expiresAfter(3600);

            try {
                $response = $this->httpClient->request('POST', "https://api.notion.com/v1/databases/{$this->formationsDatabaseId}/query", [
                    'auth_bearer' => $this->apiKey,
                    'headers' => [
                        'Notion-Version' => self::NOTION_VERSION,
                    ],
                    'json' => [
                        'sorts' => [
                            ['property' => 'Date de début', 'direction' => 'descending'],
                        ],
                    ],
                ]);

                $pages = $response->toArray()['results'];

                return array_map(fn (array $page): array => $this->mapFormation($page), $pages);
            } catch (ExceptionInterface $exception) {
                $this->logger->error('Impossible de récupérer les formations Notion.', ['exception' => $exception]);

                return [];
            }
        });
    }

    public function getTechnologies(): array
    {
        return $this->cache->get('notion_featured_technologies', function (ItemInterface $item): array {
            $item->expiresAfter(3600);

            $cursor = null;

            try {
                $response = $this->httpClient->request('POST', "https://api.notion.com/v1/databases/{$this->technologiesDatabaseId}/query", [
                    'auth_bearer' => $this->apiKey,
                    'headers' => [
                        'Notion-Version' => self::NOTION_VERSION,
                    ],
                ]);

                $pages = $response->toArray()['results'];
                $datas = [];
                foreach ($pages as $page) {
                    $datas[$page['id']] = $this->mapTechnology($page);
                }

                return $datas;
            } catch (ExceptionInterface $exception) {
                $this->logger->error('Impossible de récupérer les projets Notion.', ['exception' => $exception]);

                return [];
            }
        });
    }

    public function getSkills(): array
    {
        return $this->cache->get('notion_featured_skills', function (ItemInterface $item): array {
            $item->expiresAfter(3600);

            $cursor = null;

            try {
                $response = $this->httpClient->request('POST', "https://api.notion.com/v1/databases/{$this->skillsDatabaseId}/query", [
                    'auth_bearer' => $this->apiKey,
                    'headers' => [
                        'Notion-Version' => self::NOTION_VERSION,
                    ],
                    'json' => [
                        'sorts' => [
                            ['property' => 'Nom', 'direction' => 'descending'],
                        ],
                    ],
                ]);

                $pages = $response->toArray()['results'];
                $datas = [];
                foreach ($pages as $page) {
                    $datas[$page['id']] = $this->mapSkill($page);
                }

                return $datas;
            } catch (ExceptionInterface $exception) {
                $this->logger->error('Impossible de récupérer les projets Notion.', ['exception' => $exception]);

                return [];
            }
        });

        // dump($this->cache->get());
        // dd($skills);

        return $skills;
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
        $skills = $this->getSkills();

        return $this->mapPage($pages, technologyNames: $technologyNames, skills: $skills);
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
    private function mapPage(array $page, array $technologyNames, $skills = []): array
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

        $skillsProject = array_values(array_filter(array_map(
            static fn (array $relation) => $skills[$relation['id']] ?? null,
            $properties['Skills']['relation'] ?? [],
        )));

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
            'skills' => $skillsProject,
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

    private function mapSkill(array $page): array
    {
        $properties = $page['properties'];

        $icon = null;
        if (null != $page['icon']) {
            $icon_type = $page['icon']['type'];
            $icon = $page['icon'][$icon_type] ?? null;
        }

        return [
            'title' => $properties['Nom']['title'][0]['plain_text'] ?? '',
            'icon' => $icon,
        ];
    }

    private function mapFormation(array $page): array
    {
        $properties = $page['properties'];

        $startDate = null;
        if (null != ($properties['Date de début']['date']['start'] ?? null)) {
            $startDate = new \DateTime($properties['Date de début']['date']['start']);
        }

        $endDate = null;
        if (null != ($properties['Date de fin']['date']['start'] ?? null)) {
            $endDate = new \DateTime($properties['Date de fin']['date']['start']);
        }

        return [
            'id' => $page['id'],
            'title' => $properties['Nom']['title'][0]['plain_text'] ?? '',
            'subtitle' => $properties['Subtitle']['rich_text'][0]['plain_text'] ?? '',
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];
    }

    private function mapExperience(array $page, $skills = [], $technologies = []): array
    {
        $properties = $page['properties'];
        // dd($properties);

        $cover = null;
        if (null != $page['cover']) {
            $cover_type = $page['cover']['type'];
            $cover = $page['cover'][$cover_type]['url'];
        }

        $startDate = null;
        if (null != $properties['Date']['date']['start']) {
            $startDate = new \DateTime($properties['Date']['date']['start']);
        }

        $endDate = null;
        if (null != $properties['Date']['date']['end']) {
            $endDate = new \DateTime($properties['Date']['date']['end']);
        }

        $technologies = array_values(array_filter(array_map(
            static fn (array $relation): ?string => $technologies[$relation['id']]['title'] ?? null,
            $properties['Technologies']['relation'] ?? [],
        )));

        $skills = array_values(array_filter(array_map(
            static fn (array $relation) => $skills[$relation['id']] ?? null,
            $properties['Skills']['relation'] ?? [],
        )));

        return [
            'id' => $page['id'],
            'title' => $properties['Nom']['title'][0]['plain_text'] ?? '',
            'company' => $properties['Société']['formula']['string'] ?? '',
            'cover' => $cover,
            'color' => $properties['Couleur']['rich_text'][0]['plain_text'] ?? '',
            'contrat_type' => $properties['Type de contrat']['rich_text'][0]['plain_text'] ?? '',
            'location' => $properties['Ville']['rich_text'][0]['plain_text'] ?? '',
            'website' => $properties['website']['url'] ?? null,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'skills' => $skills,
            'technologies' => $technologies,
        ];
    }
}
