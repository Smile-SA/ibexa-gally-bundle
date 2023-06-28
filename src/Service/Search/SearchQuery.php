<?php

namespace Smile\Ibexa\Gally\Service\Search;

use Smile\Ibexa\Gally\Api\Search\SearchFilter;

class SearchQuery
{
    private ?SearchFilter $searchFilter = null;

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
        return [
            'entityType' => $this->entityType,
            'currentPage' => $this->currentPage,
            'pageSize' => $this->pageSize,
            'search' => $this->searchText,
        ];
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
     * Set a searchFilter in the search query
     * @param SearchFilter $searchFilter
     * @return void
     */
    public function setFilter(SearchFilter $searchFilter): void
    {
        $this->searchFilter = $searchFilter;
    }

    public function getFilter(): ?SearchFilter
    {
        return $this->searchFilter;
    }
}
