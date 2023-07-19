<?php

namespace Smile\Ibexa\Gally\Service\Index;

use eZ\Publish\API\Repository\ContentTypeService;
use eZ\Publish\API\Repository\Exceptions\BadStateException;
use eZ\Publish\API\Repository\Exceptions\InvalidArgumentException;
use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Exceptions\UnauthorizedException;
use eZ\Publish\API\Repository\LocationService;
use eZ\Publish\API\Repository\SearchService;
use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\API\Repository\Values\Content\Location;
use eZ\Publish\API\Repository\Values\Content\LocationQuery;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion;
use eZ\Publish\Core\Repository\SiteAccessAware\ContentService;
use Smile\Ibexa\Gally\Api\Catalog\Catalog;
use Smile\Ibexa\Gally\Api\Index\Index;
use eZ\Publish\Core\FieldType\Value;
use stdClass;
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
        private readonly LocationService $locationService,
        private readonly ContainerInterface $container,
    ) {
    }

    /**
     * [Re]Index all contents
     *
     * @param callable $logFunction
     *
     * @return void
     * @throws InvalidArgumentException
     * @throws BadStateException
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    public function reindexAll(callable $logFunction): void
    {
        $contentTypesConfig = $this->indexableContentProvider->getContentTypes();

        $localizedCatalogs = $this->catalog->getLocalizedCatalogsId();
        $localizedCatalogsCode = $this->catalog->getLocalizedCatalogsCode();
        $siteaccessGroups = $this->container->getParameter('ezpublish.siteaccess.groups');

        foreach ($localizedCatalogs as $key => $localizedCatalog) {
            $logFunction(
                'Start indexing for catalog ' . $localizedCatalogsCode[$key],
                OutputInterface::VERBOSITY_VERBOSE
            );
            $catalogCode = explode('_', $localizedCatalogsCode[$key]);
            $code = $catalogCode[1];
            $catalog = $catalogCode[0];

            $subtree = $this->container->getParameter("ezsettings.default.subtree_paths.content");
            $logFunction("Récupère le subtree par défaut $subtree", OutputInterface::VERBOSITY_VERBOSE);
            foreach ($siteaccessGroups as $group => $siteaccess) {
                if (
                    in_array($catalog, $siteaccess) && $this->container->hasParameter(
                        "ezsettings.$group.subtree_paths.content"
                    )
                ) {
                    $subtree = $this->container->getParameter("ezsettings.$group.subtree_paths.content");
                    $logFunction("Récupère le subtree du groupe $group : $subtree", OutputInterface::VERBOSITY_VERBOSE);
                }
            }
            if ($this->container->hasParameter("ezsettings.$catalog.subtree_paths.content")) {
                $subtree = $this->container->getParameter("ezsettings.$catalog.subtree_paths.content");
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
                $result = $this->sendContentsToIndex($index, $contents[$contentType], $code);
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
     * Index a subtree of contents
     *
     * @param string $subtree subtree path
     * @return void
     * @throws UnauthorizedException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     * @throws InvalidArgumentException
     * @throws BadStateException
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
            $this->sendContentWithId($content->id);
        }
    }

    /**
     * Index one content with its id
     *
     * @param int $id content id
     * @param array|null $languageCodes if null every language of the content
     * @return void
     * @throws UnauthorizedException
     * @throws BadStateException
     * @throws NotFoundException
     */
    public function sendContentWithId(int $id, array $languageCodes = null): void
    {
        $content = $this->contentService->loadContent(
            $id,
            $languageCodes
        );
        $this->sendContent($content);
    }

    /**
     * Index one Content on its site access
     * Index only content set in the config
     *
     * @param Content $content content
     * @param array|null $languageCodes if null every language of the content
     *
     * @return void
     *
     * @throws BadStateException
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    public function sendContent(Content $content, array $languageCodes = null): void
    {
        if ($this->checkContentIndexable($content->getContentType()->identifier)) {
            if ($languageCodes === null) {
                $languageCodes = $content->getVersionInfo()->languageCodes;
            }
            $siteAccessesToIndex = $this->getSiteAccessFromContent(
                $content->getVersionInfo()->getContentInfo()->getMainLocation()->pathString
            );

            $contentType = $content->getContentType();

            foreach ($siteAccessesToIndex as $siteAccess) {
                foreach ($languageCodes as $language) {
                    $indexName = 'gally_' . $siteAccess . '_' . strtolower($language) . '_' . $contentType->identifier;
                    $this->sendContentsToIndex($indexName, [$content], $language);
                }
            }
        }
    }

    /**
     * Send an ibexa content array of one content type to Gally.
     *
     * @param string $indexName indexName
     * @param Content[] $contents
     * @param string|null $languageCode if null default language of content
     * @return mixed
     * @throws BadStateException
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    public function sendContentsToIndex(string $indexName, array $contents, string $languageCode = null): mixed
    {
        $fieldTypeConfig = $this->indexableContentProvider->getFieldTypes();

        $documents = [];
        foreach ($contents as $content) {
            if ($languageCode === null) {
                $languageCode = $content->getDefaultLanguageCode();
            }
            if ($content->getVersionInfo()->getContentInfo()->isHidden) {
                continue;
            }
            $obj = new stdClass();
            $obj->id = $content->id;
            $obj->path = $this->loadLocations($content->id);
            foreach ($content->getFieldsByLanguage($languageCode) as $fieldDefinition) {
                if (in_array($fieldDefinition->fieldTypeIdentifier, $fieldTypeConfig)) {
                    $typeIdentifier = $fieldDefinition->fieldDefIdentifier;
                    $value = $content->getFieldValue($typeIdentifier, $languageCode);
                    $obj = $this->getValueForGally($obj, $value, $typeIdentifier);
                }
            }
            $documents[] = json_encode($obj);
        }

        return $this->index->sendData($indexName, $documents);
    }

    /**
     * Delete a content fromm gally with id and language
     *
     * @param int $id
     * @param array|null $languageCodes
     * @return void
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    public function deleteContent(int $id, array $languageCodes = null): void
    {
        $content = $this->contentService->loadContent(
            $id,
            $languageCodes
        );
        if ($this->checkContentIndexable($content->getContentType()->identifier)) {
            $siteAccessesToIndex = $this->getSiteAccessFromContent(
                $content->getVersionInfo()->getContentInfo()->getMainLocation()->pathString
            );

            $contentType = $content->getContentType();

            $documents[] = json_encode($id);

            if ($languageCodes === null) {
                $languageCodes = $content->getVersionInfo()->languageCodes;
            }

            foreach ($siteAccessesToIndex as $siteAccess) {
                foreach ($languageCodes as $language) {
                    $indexName = 'gally_' . $siteAccess . '_' . strtolower($language) . '_' . $contentType->identifier;
                    $this->index->deleteData($indexName, $documents);
                }
            }
        }
    }

    /**
     * Transform Ibexa value to a Gally acceptable value
     *
     * @param stdClass $obj Ibexa FieldType Value
     * @param Value $value Value
     * @param string $typeIdentifier the field of the object to pass the value
     * @return stdClass object with the value transformed
     */
    private function getValueForGally(
        stdClass $obj,
        Value $value,
        string $typeIdentifier
    ): stdClass {
        $class = get_class($value);
        switch ($class) {
            case 'eZ\Publish\Core\FieldType\Date\Value':
                /** @var \eZ\Publish\Core\FieldType\Date\Value $value */
                if (!empty($value->date)) {
                    $date = $value->date->format('Y-m-d H:i:s');
                    $obj->$typeIdentifier = $date;
                }
                break;
            case 'eZ\Publish\Core\FieldType\DateAndTime\Value':
                /** @var \eZ\Publish\Core\FieldType\DateAndTime\Value $value */
                if (!empty($value->value)) {
                    $datetime = $value->value->format('Y-m-d H:i:s');
                    $obj->$typeIdentifier = $datetime;
                }
                break;
            case 'eZ\Publish\Core\FieldType\Float\Value':
                /** @var \eZ\Publish\Core\FieldType\Float\Value $value */
                if (!empty($value->value)) {
                    $float = floatval($value->value);
                    $obj->$typeIdentifier = $float;
                }
                break;
            case 'eZ\Publish\Core\FieldType\Integer\Value':
                /** @var \eZ\Publish\Core\FieldType\Integer\Value $value */
                if (!empty($value->value)) {
                    $int = intval($value->value);
                    $obj->$typeIdentifier = $int;
                }
                break;
            case 'eZ\Publish\Core\FieldType\Checkbox\Value':
                /** @var \eZ\Publish\Core\FieldType\Checkbox\Value $value */
                if (!empty($value->bool)) {
                    $bool = $value->bool;
                    $obj->$typeIdentifier = $bool;
                }
                break;
            case 'eZ\Publish\Core\FieldType\TextLine\Value':
            case 'eZ\Publish\Core\FieldType\TextBlock\Value':
                /** @var \eZ\Publish\Core\FieldType\TextBlock\Value $value */
                if (!empty($value->text)) {
                    $text = $value->text;
                    $obj->$typeIdentifier = $text;
                }
                break;
            case 'EzSystems\EzPlatformRichText\eZ\FieldType\RichText\Value':
                /** @var \EzSystems\EzPlatformRichText\eZ\FieldType\RichText\Value $value */
                if (!empty($value->xml)) {
                    $obj->$typeIdentifier = $value->xml->textContent;
                }
                break;
            default:
        }
        return $obj;
    }

    /**
     * Check if a content type is wanted to index
     *
     * @param string $identifier content type identifier
     * @return bool
     */
    private function checkContentIndexable(string $identifier): bool
    {
        $contentTypesConfig = $this->indexableContentProvider->getContentTypes();
        if (in_array($identifier, $contentTypesConfig)) {
            return true;
        }
        return false;
    }

    /**
     * Get site access of a content
     * It check every subtree of site access
     * @param string $pathString path content
     * @return array
     */
    private function getSiteAccessFromContent(string $pathString): array
    {
        $siteAccessList = $this->container->getParameter('ezpublish.siteaccess.list');
        $siteAccessGroups = $this->container->getParameter('ezpublish.siteaccess.groups');
        $contentSubtree = $pathString;
        $siteAccessesToIndex = [];
        foreach ($siteAccessGroups as $group => $siteAccessFromGroup) {
            if ($this->container->hasParameter("ezsettings.$group.subtree_paths.content")) {
                $subtree = $this->container->getParameter("ezsettings.$group.subtree_paths.content");
                if (str_contains($contentSubtree, $subtree)) {
                    foreach ($siteAccessFromGroup as $siteAccess) {
                        $siteAccessesToIndex[] = $siteAccess;
                    }
                }
            }
        }
        foreach ($siteAccessList as $siteAccess) {
            if ($this->container->hasParameter("ezsettings.$siteAccess.subtree_paths.content")) {
                $subtree = $this->container->getParameter(
                    "ezsettings.$siteAccess.subtree_paths.content"
                );
                if (str_contains($contentSubtree, $subtree)) {
                    $siteAccessesToIndex[] = $siteAccess;
                }
            }
        }
        return $siteAccessesToIndex;
    }

    /**
     * Charge le pathstring de toutes les locations d'un contenu
     *
     * @throws BadStateException
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    private function loadLocations(int $contentId): array
    {
        $array = [];
        $contentInfo = $this->contentService->loadContentInfo($contentId);
        foreach ($this->locationService->loadLocations($contentInfo) as $location) {
            $array[] = $location->pathString;
        }
        return $array;
    }
}
