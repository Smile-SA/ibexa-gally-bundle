<?php

namespace Smile\Ibexa\Gally\Service\Index;

use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Exceptions\InvalidArgumentException;
use Ibexa\Contracts\Core\Repository\Exceptions\InvalidCriterionArgumentException;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException;
use Ibexa\Contracts\Core\Repository\SearchService;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\Repository\Values\Content\LocationQuery;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion;
use Ibexa\Core\Repository\SiteAccessAware\ContentService;
use Smile\Ibexa\Gally\Api\Catalog\Catalog;
use Smile\Ibexa\Gally\Api\Index\Index;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class IndexDocument
{
    public function __construct(
        private readonly Index $index,
        private readonly Catalog $catalog,
        private readonly IndexableContentProvider $indexableContentProvider,
        private readonly ContentTypeService $contentTypeService,
        private readonly SearchService $searchService,
        private readonly ContentService $contentService,
        private readonly ContainerInterface $container,
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
        $fieldTypeConfig = $this->indexableContentProvider->getFieldTypes();

        $localizedCatalogs = $this->catalog->getLocalizedCatalogsId();
        $localizedCatalogsCode = $this->catalog->getLocalizedCatalogsCode();
        $siteaccessGroups = $this->container->getParameter('ibexa.site_access.groups');

        foreach ($localizedCatalogs as $key => $localizedCatalog) {
            $logFunction(
                'Start indexing for catalog ' . $localizedCatalogsCode[$key],
                OutputInterface::VERBOSITY_VERBOSE
            );
            $catalogCode = explode('_', $localizedCatalogsCode[$key]);
            $code = $catalogCode[1];
            $catalog = $catalogCode[0];

            $subtree = $this->container->getParameter("ibexa.site_access.config.default.subtree_paths.content");
            $logFunction("Récupère le subtree par défaut $subtree", OutputInterface::VERBOSITY_VERBOSE);
            foreach ($siteaccessGroups as $group => $siteaccess) {
                if (
                    in_array($catalog, $siteaccess) && $this->container->hasParameter(
                        "ibexa.site_access.config.$group.subtree_paths.content"
                    )
                ) {
                    $subtree = $this->container->getParameter("ibexa.site_access.config.$group.subtree_paths.content");
                    $logFunction("Récupère le subtree du groupe $group : $subtree", OutputInterface::VERBOSITY_VERBOSE);
                }
            }
            if ($this->container->hasParameter("ibexa.site_access.config.$catalog.subtree_paths.content")) {
                $subtree = $this->container->getParameter("ibexa.site_access.config.$catalog.subtree_paths.content");
                $logFunction(
                    "Récupère le subtree du siteaccess $catalog : $subtree",
                    OutputInterface::VERBOSITY_VERBOSE
                );
            }

            $indexList = [];
            $contents = [];

            $logFunction('Load content type groups', OutputInterface::VERBOSITY_VERBOSE);
            $contentTypeGroups = $this->contentTypeService->loadContentTypeGroups();
            foreach ($contentTypeGroups as $contentTypeGroup) {
                $contentTypes = $this->contentTypeService->loadContentTypes($contentTypeGroup);

                foreach ($contentTypes as $contentType) {
                    if (!in_array($contentType->identifier, $contentTypesConfig)) {
                        continue;
                    }

                    // Create Index on metadata
                    $logFunction(
                        'Create index of ' . $contentType->identifier
                    );
                    $index = $this->index->createIndex($contentType->identifier, $localizedCatalog);
                    $indexList[$contentType->identifier] = $index['name'];
                    $contents[$contentType->identifier] = [];
                }
            }

            $query = new LocationQuery();
            $query->query = new Criterion\LogicalAnd(
                [
                    new Criterion\Visibility(Criterion\Visibility::VISIBLE),
                    new Criterion\Subtree($subtree),
                ]
            );
            $count = $this->searchService->findLocations($query)->totalCount;
            $query->limit = $count;
            $results = $this->searchService->findLocations($query);
            $contents = [];
            foreach ($results->searchHits as $searchHit) {
                /** @var Location $location */
                $location = $searchHit->valueObject;
                $content = $location->getContent();
                if (in_array($code, $content->getVersionInfo()->languageCodes)) {
                    $contents[$content->getContentType()->identifier][] = $content;
                }
            }

            foreach ($indexList as $contentType => $index) {
                $logFunction('Sent ' . count($contents[$contentType]) . ' contents ' . $contentType);
                $result = $this->sendIbexaData($index, $contents[$contentType], $fieldTypeConfig, $code);
                $logFunction(
                    'Result ' . $result
                );

                $logFunction(
                    'Install index ' . $index
                );
                $result = $this->index->installIndex($index);
                $logFunction(
                    'Result ' . $result
                );

                $logFunction(
                    'Refresh index ' . $index
                );
                $result = $this->index->refreshIndex($index);
                $logFunction(
                    'Result ' . $result
                );
            }
        }
    }

    /**
     * @throws InvalidArgumentException
     * @throws InvalidCriterionArgumentException
     */
    public function indexSubtree(string $subtree): void
    {
        $query = new LocationQuery();
        $query->query = new Criterion\LogicalAnd(
            [
                new Criterion\Visibility(Criterion\Visibility::VISIBLE),
                new Criterion\Subtree($subtree),
            ]
        );
        $count = $this->searchService->findLocations($query)->totalCount;
        $query->limit = $count;
        $results = $this->searchService->findLocations($query);
        foreach ($results->searchHits as $searchHit) {
            /** @var Location $location */
            $location = $searchHit->valueObject;
            $content = $location->getContent();
            try {
                $content = $this->contentService->loadContent(
                    $content->id,
                    $content->getVersionInfo()->languageCodes
                );
            } catch (NotFoundException | UnauthorizedException $e) {
                dump($e);
            }
            $this->index($content);
        }
    }

    /**
     * Index a list of content on a siteaccess
     *
     * @param Content $content content
     * @param array|null $languages if languages know else every language of the content
     *
     * @return void
     */
    public function index(Content $content, array $languages = null): void
    {
        $contentTypesConfig = $this->indexableContentProvider->getContentTypes();
        if (in_array($content->getContentType()->identifier, $contentTypesConfig)) {
            if ($languages === null) {
                $languages = $content->getVersionInfo()->languageCodes;
            }
            $siteaccesses = $this->container->getParameter('ibexa.site_access.list');
            $siteaccessGroups = $this->container->getParameter('ibexa.site_access.groups');
            $contentSubtree = $content->getVersionInfo()->getContentInfo()->getMainLocation()->pathString;
            $siteaccessesToIndex = [];
            foreach ($siteaccessGroups as $group => $siteaccesss) {
                if ($this->container->hasParameter("ibexa.site_access.config.$group.subtree_paths.content")) {
                    $subtree = $this->container->getParameter("ibexa.site_access.config.$group.subtree_paths.content");
                    if (str_contains($contentSubtree, $subtree)) {
                        foreach ($siteaccesss as $siteaccess) {
                            $siteaccessesToIndex[] = $siteaccess;
                        }
                    }
                }
            }
            foreach ($siteaccesses as $siteaccess) {
                if ($this->container->hasParameter("ibexa.site_access.config.$siteaccess.subtree_paths.content")) {
                    $subtree = $this->container->getParameter(
                        "ibexa.site_access.config.$siteaccess.subtree_paths.content"
                    );
                    if (str_contains($contentSubtree, $subtree)) {
                        $siteaccessesToIndex[] = $siteaccess;
                    }
                }
            }

            $fieldTypeConfig = $this->indexableContentProvider->getFieldTypes();

            $contentType = $content->getContentType();

            foreach ($siteaccessesToIndex as $siteaccess) {
                foreach ($languages as $language) {
                    $indexName = 'gally_' . $siteaccess . '_' . strtolower($language) . '_' . $contentType->identifier;
                    $this->sendIbexaData($indexName, [$content], $fieldTypeConfig, $language);
                }
            }
        }
    }

    /**
     * Send an array of one type of Ibexa contents to Gally.
     *
     * @param string $indexName
     * @param array $contents
     * @param $fieldTypeConfig
     * @param string|null $language language CODE
     * @return mixed
     */
    public function sendIbexaData(string $indexName, array $contents, $fieldTypeConfig, string $language = null): mixed
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
                        case 'Ibexa\Core\FieldType\Date\Value':
                            /** @var \Ibexa\Core\FieldType\Date\Value $value */
                            $date = $value->date->format('Y-m-d H:i:s');
                            $obj->$typeIdentifier = $date;
                            break;
                        case 'Ibexa\Core\FieldType\DateAndTime\Value':
                            /** @var \Ibexa\Core\FieldType\DateAndTime\Value $value */
                            $datetime = $value->value->format('Y-m-d H:i:s');
                            $obj->$typeIdentifier = $datetime;
                            break;
                        case 'Ibexa\Core\FieldType\Float\Value':
                            /** @var \Ibexa\Core\FieldType\Float\Value $value */
                            $float = floatval($value->value);
                            $obj->$typeIdentifier = $float;
                            break;
                        case 'Ibexa\Core\FieldType\Integer\Value':
                            /** @var \Ibexa\Core\FieldType\Integer\Value $value */
                            $int = intval($value->value);
                            $obj->$typeIdentifier = $int;
                            break;
                            //TODO pas necessaire ?
                        case 'Ibexa\Core\FieldType\Checkbox\Value':
                            /** @var \Ibexa\Core\FieldType\Checkbox\Value $value */
                            $bool = $value->bool;
                            $obj->$typeIdentifier = $bool;
                            break;
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
                            //TODO desactiver le par défaut ?
                            $obj->$typeIdentifier = $content->getFieldValue($typeIdentifier);
                    }
                }
            }
            $documents[] = json_encode($obj);
        }

        return $this->index->sendData($indexName, $documents);
    }
}
