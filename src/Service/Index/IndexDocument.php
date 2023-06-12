<?php

namespace Smile\Ibexa\Gally\Service\Index;

use Ibexa\Contracts\Core\Repository\Exceptions\InvalidArgumentException;
use Ibexa\Contracts\Core\Repository\Exceptions\InvalidCriterionArgumentException;
use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\SearchService;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\Repository\Values\Content\LocationQuery;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion;
use Smile\Ibexa\Gally\Api\Catalog\Catalog;
use Smile\Ibexa\Gally\Api\Index\Index;
use Symfony\Component\Console\Output\OutputInterface;

class IndexDocument
{
    public function __construct(
        private readonly Index $index,
        private readonly Catalog $catalog,
        private readonly IndexableContentProvider $indexableContentProvider,
        private readonly ContentTypeService $contentTypeService,
        private readonly SearchService $searchService,
    ) {
    }

    /**
     * [Re]Index all contents
     *
     * @param callable $logFunction
     *
     * @return void
     * @throws InvalidCriterionArgumentException|InvalidArgumentException
     */
    public function reindexAll(callable $logFunction): void
    {
        $contentTypesConfig = $this->indexableContentProvider->getContentTypes();
        $fieldTypeConfig    = $this->indexableContentProvider->getFieldTypes();

        $localizedCatalogs = $this->catalog->getLocalizedCatalogsId();
        $localizedCatalogsCode = $this->catalog->getLocalizedCatalogsCode();

        foreach ($localizedCatalogs as $key => $localizedCatalog) {
            $catalogCode = explode('_', $localizedCatalogsCode[$key]);
            $code = $catalogCode[array_key_last($catalogCode)];
            $logFunction('Load content type groups', OutputInterface::VERBOSITY_VERBOSE);
            $contentTypeGroups = $this->contentTypeService->loadContentTypeGroups();

            $logFunction('For each content types groups', OutputInterface::VERBOSITY_VERBOSE);
            foreach ($contentTypeGroups as $contentTypeGroup) {
                $logFunction(
                    'Get all contents types of group ' . $contentTypeGroup->identifier,
                    OutputInterface::VERBOSITY_VERBOSE
                );
                $contentTypes = $this->contentTypeService->loadContentTypes($contentTypeGroup);

                foreach ($contentTypes as $contentType) {
                    if (!in_array($contentType->identifier, $contentTypesConfig)) {
                        continue;
                    }

                    // Create Index on metadata
                    $logFunction(
                        'Create index of ' . $contentType->identifier
                    );
                    $index     = $this->index->createIndex($contentType->identifier, $localizedCatalog);
                    $indexName = $index['name'];

                    // Send content of the content type
                    $query        = new LocationQuery();
                    $query->query = new Criterion\LogicalAnd(
                        [
                            new Criterion\Visibility(Criterion\Visibility::VISIBLE),
                            new Criterion\ContentTypeIdentifier([$contentType->identifier]),
                        ]
                    );
                    $count        = $this->searchService->findLocations($query)->totalCount;
                    $query->limit = $count;
                    $results      = $this->searchService->findLocations($query);
                    $contents     = [];
                    foreach ($results->searchHits as $searchHit) {
                        /** @var Location $location */
                        $location   = $searchHit->valueObject;
                        $content    = $location->getContent();
                        if (in_array($code, $content->getVersionInfo()->languageCodes)) {
                            $contents[] = $content;
                        }
                    }
                    $logFunction('Sent ' . count($contents) . ' contents ' . $contentType->identifier);
                    $this->sendIbexaData($indexName, $contents, $fieldTypeConfig, $code);

                    $logFunction(
                        'Install index ' . $indexName
                    );
                    $this->index->installIndex($indexName);

                    $logFunction(
                        'Refresh index ' . $indexName
                    );
                    $this->index->refreshIndex($indexName);
                }
            }
        }
    }

    /**
     * Index a list of content on a siteaccess
     *
     * @param string $siteaccess
     * @param ...$contents
     *
     * @return void
     */
    public function index(string $siteaccess, ...$contents): void
    {
        $fieldTypeConfig = $this->indexableContentProvider->getFieldTypes();

        $contentType = $contents[0]->getContentType();
        $language    = $contents[0]->getDefaultLanguageCode();
        $indexName   = 'gally_' . $siteaccess . '_' . strtolower($language) . '_' . $contentType->identifier;

        $this->sendIbexaData($indexName, $contents, $fieldTypeConfig);
    }

    /**
     * Send an array of one type of Ibexa contents to Gally.
     *
     * @param string $indexName
     * @param array $contents
     * @param $fieldTypeConfig
     * @param string|null $language language CODE
     * @return void
     */
    public function sendIbexaData(string $indexName, array $contents, $fieldTypeConfig, string $language = null): void
    {
        $documents = [];
        /** @var Content $content */
        foreach ($contents as $content) {
            if ($language === null) {
                $language = $content->getDefaultLanguageCode();
            }
            $obj = new \stdClass();
            $obj->id = $content->id;
            $obj->path = $content->contentInfo->getMainLocation()->pathString;
            /** @var \Ibexa\Contracts\Core\Repository\Values\Content\Field $fieldDefinition */
            foreach ($content->getFieldsByLanguage($language) as $fieldDefinition) {
                if (\in_array($fieldDefinition->fieldTypeIdentifier, $fieldTypeConfig)) {
                    $typeIdentifier = $fieldDefinition->fieldDefIdentifier;
                    $value = $content->getFieldValue($typeIdentifier, $language);
                    $text = null;
                    $class = \get_class($value);
                    switch ($class) {
                        case 'Ibexa\Core\FieldType\TextLine\Value':
                        case 'Ibexa\Core\FieldType\TextBlock\Value':
                            /** @var \Ibexa\Core\FieldType\TextBlock\Value $value */
                            $text = $value->text;
                            $obj->$typeIdentifier = $text;
                            break;
                        case 'Ibexa\FieldTypeRichText\FieldType\RichText\Value':
                            /** @var \Ibexa\FieldTypeRichText\FieldType\RichText\Value $value */
                            $obj->$typeIdentifier = $value->xml->textContent;
                            break;
                        default:
                            $obj->$typeIdentifier = $content->getFieldValue($typeIdentifier);
                    }
                }
            }
            $documents[] = json_encode($obj);
        }
        $this->index->sendData($indexName, $documents);
    }
}
