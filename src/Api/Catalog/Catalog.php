<?php

namespace Smile\Ibexa\Gally\Api\Catalog;

use Gally\Rest\Model\Catalog as CatalogModel;
use Gally\Rest\Model\LocalizedCatalog;
use Smile\Ibexa\Gally\Service\Client\Client;

class Catalog
{
    /**
     * @var \Gally\Rest\Model\Catalog[]
     */
    private array $catalogsByCode = [];

    /**
     * @var \Gally\Rest\Model\LocalizedCatalog[]
     */
    private array $localizedCatalogsByCode = [];

    /**
     * @var \Gally\Rest\Model\Catalog[]
     */
    private array $catalogsById = [];

    /**
     * @var \Gally\Rest\Model\LocalizedCatalog[]
     */
    private array $localizedCatalogsById = [];

    public function __construct(private readonly Client $client)
    {
        $this->getCatalogs();
    }

    private function getCatalogs(): void
    {
        /** @var \Gally\Rest\Model\CatalogCatalogRead[] $catalogs */
        $catalogs = $this->client->query(\Gally\Rest\Api\CatalogApi::class, 'getCatalogCollection');

        foreach ($catalogs as $catalog) {
            $this->catalogsByCode[$catalog->getCode()] = $catalog;
            $this->catalogsById[$catalog->getId()] = $catalog;
        }

        /** @var \Gally\Rest\Model\LocalizedCatalogCatalogRead[] $localizedCatalogs */
        $localizedCatalogs = $this->client->query(
            \Gally\Rest\Api\LocalizedCatalogApi::class,
            'getLocalizedCatalogCollection'
        );

        foreach ($localizedCatalogs as $localizedCatalog) {
            $this->localizedCatalogsByCode[$localizedCatalog->getCode()] = $localizedCatalog;
            $this->localizedCatalogsById[$localizedCatalog->getId()] = $localizedCatalog;
        }
    }

    public function createCatalogIfNotExists(CatalogModel $input)
    {
        // Load all catalogs to be able to check if the asked catalog exists.
        $this->getCatalogs();

        if (!$input->valid()) {
            throw new \LogicException('Missing properties for ' . \get_class($input) . ' : ' . implode(',', $input->listInvalidProperties()));
        }

        if ($input->getCode()) {
            // Check if catalog already exists.
            if (!isset($this->catalogsByCode[$input->getCode()])) {
                // Create it if needed. Also save it locally for later use.
                /** @var \Gally\Rest\Model\CatalogCatalogRead $catalog */
                $catalog = $this->client->query(
                    \Gally\Rest\Api\CatalogApi::class,
                    'postCatalogCollection',
                    $input
                );
                if ($catalog !== null) {
                    $this->catalogsByCode[$catalog->getCode()] = $catalog;
                    $this->catalogsById[$catalog->getId()] = $catalog;
                }
            }
        }

        return $this->catalogsByCode[$input->getCode()] ?? null;
    }

    public function createLocalizedCatalogIfNotExists(LocalizedCatalog $input)
    {
        // Load all catalogs to be able to check if the asked catalog exists.
        $this->getCatalogs();

        if (!$input->valid()) {
            throw new \LogicException('Missing properties for ' . \get_class($input) . ' : ' . implode(',', $input->listInvalidProperties()));
        }

        if ($input->getCode()) {
            // Check if catalog already exists.
            if (!isset($this->localizedCatalogsByCode[$input->getCode()])) {
                // Create it if needed. Also save it locally for later use.
                /** @var \Gally\Rest\Model\LocalizedCatalogCatalogRead $localizedCatalog */
                $localizedCatalog = $this->client->query(
                    \Gally\Rest\Api\LocalizedCatalogApi::class,
                    'postLocalizedCatalogCollection',
                    $input
                );
                // Some locale doesn't exist in Gally so use fallback en_GB
                if ($localizedCatalog === null) {
                    $input->setLocalName($input->getLocale());
                    $input->setLocale('en_GB');
                    $localizedCatalog = $this->client->query(
                        \Gally\Rest\Api\LocalizedCatalogApi::class,
                        'postLocalizedCatalogCollection',
                        $input
                    );
                }
                if ($localizedCatalog !== null) {
                    $this->localizedCatalogsByCode[$localizedCatalog->getCode()] = $localizedCatalog;
                    $this->localizedCatalogsById[$localizedCatalog->getId()] = $localizedCatalog;
                }
            }
        }

        return $this->localizedCatalogsByCode[$input->getCode()] ?? null;
    }

    /**
     * Delete catalog using id.
     *
     * @param string $id
     *
     * @return void
     */
    public function deleteCatalog(string $id): void
    {
        $this->client->query(
            \Gally\Rest\Api\CatalogApi::class,
            'deleteCatalogItem',
            $id
        );
    }

    /**
     * @return string[] all catalogs id
     */
    public function getCatalogsId(): array
    {
        $this->getCatalogs();

        return array_keys($this->catalogsById);
    }

    /**
     * @return string[] all localized catalogs id
     */
    public function getLocalizedCatalogsId(): array
    {
        $this->getCatalogs();

        return array_keys($this->localizedCatalogsById);
    }

    /**
     * @return string[] all localized catalogs code
     */
    public function getLocalizedCatalogsCode(): array
    {
        $this->getCatalogs();

        return array_keys($this->localizedCatalogsByCode);
    }

    public function getLocalizedCatalogs(): array
    {
        $this->getCatalogs();

        $catalogs = [];

        $localizedCatalogsCode = $this->getLocalizedCatalogsCode();
        foreach ($this->getLocalizedCatalogsId() as $key => $id) {
            $catalogs[$id] = $localizedCatalogsCode[$key];
        }

        return $catalogs;
    }

    /**
     * Found the localized catalog id using siteaccess and language code
     * Example : getLocalizedCatalogByName('site', 'eng-GB')
     *
     * @param string $siteaccess
     * @param string $languageCode
     *
     * @return int -1 if not found
     */
    public function getLocalizedCatalogByName(string $siteaccess, string $languageCode): int
    {
        $id = -1;
        $name = $siteaccess . '_' . $languageCode;

        $localizedCatalogs = array_flip($this->getLocalizedCatalogs());

        if (!empty($localizedCatalogs[$name])) {
            $id = $localizedCatalogs[$name];
        }

        return $id;
    }
}
