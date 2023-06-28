<?php

namespace Smile\Ibexa\Gally\Service\Search;

use Ibexa\Core\Repository\SiteAccessAware\ContentService;
use Smile\Ibexa\Gally\Api\Catalog\Catalog;
use Smile\Ibexa\Gally\Api\Search\Search;

class SearchService
{
    public function __construct(
        private readonly Search $searchApi,
        private readonly Catalog $catalog,
        private readonly ContentService $contentService,
    ) {
    }

    /**
     * Search in Gally with a searchQuery
     *
     * @param SearchQuery $searchQuery the search query
     * @return SearchResult search result with a list of results
     */
    public function find(SearchQuery $searchQuery): SearchResult
    {
        $variables = $searchQuery->getVariables();
        $variables["localizedCatalog"] = (string)$this->catalog->getLocalizedCatalogByName(
            $searchQuery->getSiteAccess(),
            $searchQuery->getLanguageCode()
        );

        return new SearchResult(
            $this->contentService,
            $this->searchApi->search($variables, $searchQuery->getFilter()),
            $searchQuery->getLanguageCode()
        );
    }
}
