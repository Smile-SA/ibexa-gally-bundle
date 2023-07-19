<?php

namespace Smile\Ibexa\Gally\Service\Search;

use Smile\Ibexa\Gally\Service\Search\Filters\Filter;
use Smile\Ibexa\Gally\Service\Search\Sort\SortDirection;
use Smile\Ibexa\Gally\Service\Search\Sort\SortOrder;

class SearchQuery
{
    /** @var Filter[] */
    private array $filters = [];

    private ?SortOrder $sortOrder = null;

    public function __construct(
        private string $siteAccess,
        private string $languageCode,
        private string $entityType,
        private string $searchText,
        private int $currentPage = 1,
        private int $pageSize = 100,
    ) {
    }

    /**
     * @param string $siteAccess
     */
    public function setSiteAccess(string $siteAccess): void
    {
        $this->siteAccess = $siteAccess;
    }

    /**
     * @param string $languageCode
     */
    public function setLanguageCode(string $languageCode): void
    {
        $this->languageCode = $languageCode;
    }

    /**
     * @param string $entityType
     */
    public function setEntityType(string $entityType): void
    {
        $this->entityType = $entityType;
    }

    /**
     * @param string $searchText
     */
    public function setSearchText(string $searchText): void
    {
        $this->searchText = $searchText;
    }

    /**
     * @param int $currentPage
     */
    public function setCurrentPage(int $currentPage): void
    {
        $this->currentPage = $currentPage;
    }

    /**
     * @param int $pageSize
     */
    public function setPageSize(int $pageSize): void
    {
        $this->pageSize = $pageSize;
    }

    /**
     * Get variables to use in search service
     *
     * @return array
     */
    public function getVariables(): array
    {
        $variables = [
            'entityType' => $this->entityType,
            'currentPage' => $this->currentPage,
            'pageSize' => $this->pageSize,
            'search' => $this->searchText,
        ];

        if ($this->sortOrder !== null) {
            $variables["sort"] = $this->sortOrder->getVariable();
        }

        return $variables;
    }

    /**
     * @return string
     */
    public function getSiteAccess(): string
    {
        return $this->siteAccess;
    }

    /**
     * @return string
     */
    public function getLanguageCode(): string
    {
        return $this->languageCode;
    }

    /**
     * Add a filter in the search query
     * @param Filter $filter
     * @param Filter ...$moreFilters
     * @return void
     */
    public function addFilter(Filter $filter, Filter ...$moreFilters): void
    {
        $this->filters[] = $filter;
        foreach ($moreFilters as $aFilter) {
            $this->filters[] = $aFilter;
        }
    }

    /**
     * @return Filter[]
     */
    public function getFilter(): array
    {
        return $this->filters;
    }

    public function setSortOrder(string $field, SortDirection $sortDirection): void
    {
        $this->sortOrder = new SortOrder($field, $sortDirection);
    }
}
