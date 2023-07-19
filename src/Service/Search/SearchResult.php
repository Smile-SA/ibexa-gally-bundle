<?php

namespace Smile\Ibexa\Gally\Service\Search;

use eZ\Publish\Core\Repository\ContentService;

class SearchResult
{
    private mixed $jsonRawResponse;
    /** @var Result[] */
    private array $results;
    private int $lastPage;
    private int $itemsPerPage;
    private int $totalCount;

    public function __construct(
        private readonly ContentService $contentService,
        $jsonResponse,
        private string $languageCode
    ) {
        $this->results = [];
        $this->jsonRawResponse = $jsonResponse;
        $resultArray = $this->jsonRawResponse["data"]["documents"]["collection"];
        foreach ($resultArray as $result) {
            $this->results[] = new Result($this->contentService, $result, $this->languageCode);
        }
        $this->lastPage = $this->jsonRawResponse["data"]["documents"]["paginationInfo"]["lastPage"];
        $this->itemsPerPage = $this->jsonRawResponse["data"]["documents"]["paginationInfo"]["itemsPerPage"];
        $this->totalCount = $this->jsonRawResponse["data"]["documents"]["paginationInfo"]["totalCount"];
    }

    /**
     * Get JSON raw response
     *
     * @return mixed
     */
    public function getJsonRawResponse(): mixed
    {
        return $this->jsonRawResponse;
    }

    /**
     * Get array of results
     * @return Result[]
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Get last page
     *
     * @return int
     */
    public function getLastPage(): int
    {
        return $this->lastPage;
    }

    /**
     * Get number of results per page
     *
     * @return int
     */
    public function getItemsPerPage(): int
    {
        return $this->itemsPerPage;
    }

    /**
     * Get total count of results
     *
     * @return int
     */
    public function getTotalCount(): int
    {
        return $this->totalCount;
    }
}
