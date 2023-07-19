<?php

namespace Smile\Ibexa\Gally\Service\Search;

use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Exceptions\UnauthorizedException;
use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\Core\Repository\ContentService;

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
