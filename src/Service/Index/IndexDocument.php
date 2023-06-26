<?php

namespace Smile\Ibexa\Gally\Service\Index;

use Ibexa\Contracts\Core\FieldType\Value;
use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\Exceptions\BadStateException;
use Ibexa\Contracts\Core\Repository\Exceptions\InvalidArgumentException;
use Ibexa\Contracts\Core\Repository\Exceptions\InvalidCriterionArgumentException;
use Ibexa\Contracts\Core\Repository\Exceptions\NotFoundException;
use Ibexa\Contracts\Core\Repository\Exceptions\UnauthorizedException;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\SearchService;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\Repository\Values\Content\LocationQuery;
use Ibexa\Contracts\Core\Repository\Values\Content\Query\Criterion;
use Ibexa\Core\Repository\SiteAccessAware\ContentService;
use Smile\Ibexa\Gally\Api\Catalog\Catalog;
use Smile\Ibexa\Gally\Api\Index\Index;
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
     * @throws InvalidCriterionArgumentException
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
        $siteAccessList = $this->container->getParameter('ibexa.site_access.list');
        $siteAccessGroups = $this->container->getParameter('ibexa.site_access.groups');
        $contentSubtree = $pathString;
        $siteAccessesToIndex = [];
        foreach ($siteAccessGroups as $group => $siteAccessFromGroup) {
            if ($this->container->hasParameter("ibexa.site_access.config.$group.subtree_paths.content")) {
                $subtree = $this->container->getParameter("ibexa.site_access.config.$group.subtree_paths.content");
                if (str_contains($contentSubtree, $subtree)) {
                    foreach ($siteAccessFromGroup as $siteAccess) {
                        $siteAccessesToIndex[] = $siteAccess;
                    }
                }
            }
        }
        foreach ($siteAccessList as $siteAccess) {
            if ($this->container->hasParameter("ibexa.site_access.config.$siteAccess.subtree_paths.content")) {
                $subtree = $this->container->getParameter(
                    "ibexa.site_access.config.$siteAccess.subtree_paths.content"
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
