<?php

namespace App\Referential;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Client de l'API Recherche d'entreprises (7 appels/s max). */
final readonly class ReferentialClient
{
    private const URL = 'https://recherche-entreprises.api.gouv.fr/search';
    private const PER_PAGE = 25;

    public function __construct(
        private HttpClientInterface $httpClient,
        private int $delayMicroseconds = 150_000,
    ) {
    }

    /**
     * @param array<string, string> $filters paramètres de l'API (activite_principale, departement, code_postal, tranche_effectif_salarie…)
     *
     * @return \Generator<ReferentialCompany>
     */
    public function search(array $filters, ?string $query = null, int $limit = 100): \Generator
    {
        $count = 0;

        for ($page = 1;; ++$page) {
            $params = array_filter(['q' => $query]) + $filters + ['etat_administratif' => 'A'];
            $data = $this->httpClient->request('GET', self::URL, [
                'query' => $params + ['per_page' => self::PER_PAGE, 'page' => $page, 'limite_matching_etablissements' => 100],
                'timeout' => 20,
            ])->toArray();
            usleep($this->delayMicroseconds);

            $results = \is_array($data['results'] ?? null) ? $data['results'] : [];
            foreach ($results as $item) {
                $company = \is_array($item) ? ReferentialCompany::fromApi($item) : null;
                if (null === $company) {
                    continue;
                }

                yield $company;
                if (++$count >= $limit) {
                    return;
                }
            }

            $totalPages = \is_int($data['total_pages'] ?? null) ? $data['total_pages'] : 0;
            if ([] === $results || $page >= $totalPages) {
                return;
            }
        }
    }
}
