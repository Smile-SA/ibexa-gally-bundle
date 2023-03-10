<?php

namespace Smile\Ibexa\Gally\Api\Metadata;

use Gally\Rest\Model\Metadata as MetadataModel;
use Smile\Ibexa\Gally\Service\Client\Client;

class Metadata
{
    private array $metadataByEntityType = [];

    private array $metadataById = [];

    public function __construct(private readonly Client $client)
    {
        $this->getMetadata();
    }

    /**
     * @return int[] all metadatum ids
     */
    public function getMetadatumIds(): array
    {
        $this->getMetadata();

        return array_keys($this->metadataById);
    }

    public function getMetadataByEntityType(): array
    {
        $this->getMetadata();

        return $this->metadataByEntityType;
    }

    /**
     * @throws \Exception
     */
    public function getMetadataIdByEntityType($entityType)
    {
        if (!isset($this->metadataByEntityType[$entityType])) {
            $this->getMetadata();
            if (!isset($this->metadataByEntityType[$entityType])) {
                throw new \Exception('Cannot find Metadata for entity ' . $entityType);
            }
        }

        return $this->metadataByEntityType[$entityType]->getId();
    }

    /**
     * @throws \Exception
     */
    public function getMetadataEntityTypeById($metadataId)
    {
        if (!isset($this->metadataById[$metadataId])) {
            $this->getMetadata();
            if (!isset($this->metadataById[$metadataId])) {
                throw new \Exception('Cannot find Metadata with id ' . $metadataId);
            }
        }

        return $this->metadataById[$metadataId]->getEntity();
    }

    private function getMetadata(): void
    {
        /** @var \Gally\Rest\Model\Metadata[] $metadata */
        $metadata = $this->client->query(\Gally\Rest\Api\MetadataApi::class, 'getMetadataCollection');

        /** @var \Gally\Rest\Model\Metadata $metadatum */
        foreach ($metadata as $metadatum) {
            $this->metadataByEntityType[$metadatum->getEntity()] = $metadatum;
            $this->metadataById[$metadatum->getId()] = $metadatum;
        }
    }

    public function createMetadata(MetadataModel ...$metadata): void
    {
        $this->client->query(
            \Gally\Rest\Api\MetadataApi::class,
            'postMetadataCollection',
            ...$metadata
        );
    }

    public function deleteMetadata(int $id): void
    {
        $this->client->query(
            \Gally\Rest\Api\MetadataApi::class,
            'deleteMetadataItem',
            $id
        );
    }
}
