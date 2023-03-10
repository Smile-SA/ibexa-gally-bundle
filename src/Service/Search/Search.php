<?php

namespace Smile\Ibexa\Gally\Service\Search;

use Smile\Ibexa\Gally\Api\Search\Search as SearchApi;
use Smile\Ibexa\Gally\Api\Search\SearchFilter;

class Search
{
    public function __construct(private readonly SearchApi $searchApi)
    {
    }

    /**
     * Search method.
     *
     * @param int $localizedCatalog
     * @param string $searchText
     * @param string $entityType
     * @param int $currentPage default 1
     * @param int $pageSize default 10
     * @param SearchFilter|null $filter you need to build the filter before
     *
     * @return mixed
     */
    public function search(
        int $localizedCatalog,
        string $searchText,
        string $entityType,
        int $currentPage = 1,
        int $pageSize = 10,
        SearchFilter $filter = null
    ): mixed {
        $variables = [
            'localizedCatalog' => (string)$localizedCatalog,
            'entityType' => $entityType,
            'currentPage' => $currentPage,
            'pageSize' => $pageSize,
            'search' => $searchText,
        ];

        return $this->searchApi->search($variables, $filter);
    }
}
