<?php

namespace Smile\Ibexa\Gally\Service\Search;

use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Core\Repository\SiteAccessAware\ContentService;

class Result
{
    private mixed $jsonRaw;
    private string $documentId;
    private int $score;
    private array $source;
    private int $contentId;

    public function __construct(
        private readonly ContentService $contentService,
        mixed $json,
        private readonly string $languageCode
    ) {
        $this->jsonRaw = $json;
        $this->documentId = $this->jsonRaw["id"];
        $this->score = $this->jsonRaw["score"];
        $this->source = $this->jsonRaw["source"];
        $this->contentId = $this->source["id"];
    }

    /**
     * Get raw JSON response
     *
     * @return mixed
     */
    public function getJsonRaw(): mixed
    {
        return $this->jsonRaw;
    }

    /**
     * Get Gally document ID
     *
     * @return string
     */
    public function getDocumentId(): string
    {
        return $this->documentId;
    }

    /**
     * Get search score
     *
     * @return int
     */
    public function getScore(): int
    {
        return $this->score;
    }

    /**
     * Get JSON content field
     *
     * @return array
     */
    public function getSource(): array
    {
        return $this->source;
    }

    /**
     * Get content ID
     *
     * @return int
     */
    public function getContentId(): int
    {
        return $this->contentId;
    }

    /**
     * Get language code of content
     *
     * @return string
     */
    public function getLanguageCode(): string
    {
        return $this->languageCode;
    }

    /**
     * Get Ibexa content
     *
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    public function getIbexaContent(): Content
    {
        return $this->contentService->loadContent(
            $this->contentId,
            [$this->languageCode]
        );
    }
}
