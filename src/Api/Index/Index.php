<?php

namespace Smile\Ibexa\Gally\Api\Index;

use Gally\Rest\Model\IndexCreateIndexInputCreate;
use Gally\Rest\Model\IndexDocument;
use Smile\Ibexa\Gally\Service\Client\Client;

class Index
{
    private array $indicesByName = [];

    public function __construct(private readonly Client $client)
    {
        $this->getIndices();
    }

    /**
     * @return string[] all index names
     */
    public function getIndexNames(): array
    {
        $this->getIndices();

        return array_keys($this->indicesByName);
    }

    /**
     * Used for full the indicesByName array.
     * @return void
     */
    private function getIndices(): void
    {
        /** @var \Gally\Rest\Model\IndexList[] $index */
        $index = $this->client->query(\Gally\Rest\Api\IndexApi::class, 'getIndexCollection');

        /** @var \Gally\Rest\Model\IndexList $indices */
        foreach ($index as $indices) {
            $this->indicesByName[$indices->getName()] = $indices;
        }
    }

    /**
     * Delete index using name.
     *
     * @param string $name
     *
     * @return void
     */
    public function deleteIndex(string $name): void
    {
        $this->client->query(
            \Gally\Rest\Api\IndexApi::class,
            'deleteIndexItem',
            $name
        );
    }

    /**
     * Create index of metadata on catalog.
     *
     * @param string $metadata
     * @param string $localizedCatalogId
     *
     * @return array|mixed|null the request
     */
    public function createIndex(string $metadata, string $localizedCatalogId): mixed
    {
        $indexInput =
            new IndexCreateIndexInputCreate([
                'entityType' => $metadata,
                'localizedCatalog' => $localizedCatalogId,
            ]);

        return $this->client->query(
            \Gally\Rest\Api\IndexApi::class,
            'postIndexCollection',
            $indexInput
        );
    }

    /**
     * Install index with the name.
     *
     * @param string $indexName
     *
     * @return array|mixed|null the request
     */
    public function installIndex(string $indexName): mixed
    {
        return $this->client->query(
            \Gally\Rest\Api\IndexApi::class,
            'installIndexItem',
            $indexName,
            '{}'
        );
    }

    /**
     * Refresh index with the name.
     *
     * @param string $indexName
     *
     * @return array|mixed|null the request
     */
    public function refreshIndex(string $indexName): mixed
    {
        return $this->client->query(
            \Gally\Rest\Api\IndexApi::class,
            'refreshIndexItem',
            $indexName,
            '{}'
        );
    }

    /**
     * Send data to gally.
     *
     * @param string $indexName
     * @param array $documents
     *
     * @return mixed
     */
    public function sendData(string $indexName, array $documents): mixed
    {
        $indexDocument = new IndexDocument([
            'indexName' => $indexName,
            'documents' => $documents,
        ]);

        return $this->client->query(
            \Gally\Rest\Api\IndexDocumentApi::class,
            'postIndexDocumentCollection',
            $indexDocument
        );
    }
}
