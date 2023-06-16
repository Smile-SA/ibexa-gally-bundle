<?php

namespace Smile\Ibexa\Gally\Service\GallyStructure;

use Gally\Rest\Model\Catalog as CatalogModel;
use Gally\Rest\Model\LocalizedCatalog;
use Gally\Rest\Model\Metadata as MetadataModel;
use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Core\Repository\Values\ContentType\FieldDefinition;
use InvalidArgumentException;
use Smile\Ibexa\Gally\Api\Catalog\Catalog;
use Smile\Ibexa\Gally\Api\Index\Index;
use Smile\Ibexa\Gally\Api\Metadata\Metadata;
use Smile\Ibexa\Gally\Api\SourceField\SourceField;
use Smile\Ibexa\Gally\Service\Index\IndexableContentProvider;
use Smile\Ibexa\Gally\Service\Metadata\SourceFieldMappingProvider;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Manage the Gally structure (update and purge)
 */
class GallyStructureManager
{
    // Default metadata, if delete gally don't work
    private array $defaultEntityType = [
        'product',
        'category'
    ];

    public function __construct(
        private readonly Metadata $metadata,
        private readonly Index $index,
        private readonly Catalog $catalog,
        private readonly SourceField $sourceField,
        private readonly IndexableContentProvider $indexableContentProvider,
        private readonly SourceFieldMappingProvider $sourceFieldMappingProvider,
        private readonly ContentTypeService $contentTypeService,
        private readonly ContainerInterface $container,
    ) {
    }

    /**
     * Purge all contents, metadata, catalog in Gally
     * Purge only contents created by this bundle not the default product and category
     *
     * @param callable $logFunction
     *
     * @return void
     */
    public function purge(callable $logFunction): void
    {
        foreach ($this->metadata->getMetadataByEntityType() as $type => $id) {
            if (in_array($type, $this->defaultEntityType)) {
                continue;
            }
            $logFunction('Delete metadata id: ' . $id["id"]);
            $this->metadata->deleteMetadata($id["id"]);
        }

        foreach ($this->index->getIndexNames() as $indexName) {
            if (str_contains($indexName, "pcr")) {
                $logFunction('Delete index : ' . $indexName);
                $this->index->deleteIndex($indexName);
            }
        }

        $catalogs = $this->catalog->getCatalogsId();
        foreach ($catalogs as $catalog) {
            $logFunction('Delete catalog id: ' . $catalog);
            $this->catalog->deleteCatalog($catalog);
        }
    }

    /**
     * Create or update the Gally structure for index Ibexa content
     *
     * @param callable $logFunction
     *
     * @throws \Exception
     */
    public function update(callable $logFunction): void
    {
        $conversionMap = $this->container->getParameter('ibexa.locale.conversion_map');
        $logFunction('Creating Catalogs and localized catalogs');
        $catalogs = $this->container->getParameter('ibexa.site_access.list');
        foreach ($catalogs as $catalog) {
            $logFunction('Create catalog ' . $catalog);
            $tmpCatalog = $this->catalog->createCatalogIfNotExists(
                new CatalogModel([
                    'name' => $catalog,
                    'code' => $catalog,
                ])
            );
            $languages = $this->container->getParameter("ibexa.site_access.config.$catalog.languages");
            foreach ($languages as $language) {
                $logFunction('Create language ' . $language);
                $languageCode = null;
                // Gally format : en_GB, Ibexa format: eng-GB
                if (!empty($conversionMap[$language])) {
                    $languageCode = $conversionMap[$language];
                }
                if ($languageCode === null) {
                    throw new InvalidArgumentException("The code language : $language is not in the conversion map :/");
                }
                $logFunction('Create language ' . $language . ' code : ' . $languageCode);
                $this->catalog->createLocalizedCatalogIfNotExists(
                    new LocalizedCatalog([
                        'name' => $catalog . ' ' . $language,
                        'code' => $catalog . '_' . $language,
                        'locale' => $languageCode,
                        'currency' => 'EUR',
                        'catalog' => '/catalogs/' . $tmpCatalog->getId(),
                        'isDefault' => true,
                    ])
                );
            }
        }

        $logFunction('Load content type groups');
        $contentTypeGroups = $this->contentTypeService->loadContentTypeGroups();

        $logFunction('For each content types groups');
        foreach ($contentTypeGroups as $contentTypeGroup) {
            $logFunction(
                'Get all contents types of group ' . $contentTypeGroup->identifier,
                OutputInterface::VERBOSITY_VERBOSE
            );
            $contentTypes = $this->contentTypeService->loadContentTypes($contentTypeGroup);

            foreach ($contentTypes as $contentType) {
                if (!\in_array($contentType->identifier, $this->indexableContentProvider->getContentTypes())) {
                    continue;
                }
                // Create metadata from content type
                $logFunction(
                    'Create metadata ' . $contentType->identifier,
                    OutputInterface::VERBOSITY_VERBOSE
                );
                $metadata = new MetadataModel([
                    'entity' => $contentType->identifier,
                ]);
                $this->metadata->createMetadata($metadata);

                // Create ID and Path source field
                $this->sourceField->createIdSourceField($contentType->identifier);
                $this->sourceField->createPathSourceField($contentType->identifier);

                // Create source field from fields definition of content type
                /** @var FieldDefinition $fieldDefinition */
                foreach ($contentType->getFieldDefinitions() as $fieldDefinition) {
                    if (
                        \in_array(
                            $fieldDefinition->fieldTypeIdentifier,
                            $this->indexableContentProvider->getFieldTypes()
                        )
                    ) {
                        $logFunction(
                            'Create source field ' . $fieldDefinition->identifier,
                            OutputInterface::VERBOSITY_VERBOSE
                        );
                        $fieldConfig = [];
                        if (!empty($this->sourceFieldMappingProvider->getSourceFieldMapping()[$fieldDefinition->identifier])) {
                            $fieldConfig = $this->sourceFieldMappingProvider->getSourceFieldMapping()[$fieldDefinition->identifier];
                        }

                        $this->sourceField->addSourceField(
                            $contentType->identifier,
                            $fieldDefinition->identifier,
                            $fieldDefinition->getName(),
                            $this->sourceField->getType($fieldDefinition),
                            $fieldConfig['isSearchable'] ?? true,
                            $fieldConfig['weight'] ?? 1,
                            $fieldConfig['isSpellchecked'] ?? false,
                            $fieldConfig['isFilterable'] ?? false,
                            $fieldConfig['isSortable'] ?? false,
                            $fieldConfig['isUsedForRules'] ?? false,
                        );
                    }
                }
            }
        }
    }
}
